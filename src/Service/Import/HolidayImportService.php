<?php

namespace App\Service\Import;

use App\DTO\HolidayCandidate;
use App\DTO\ImportResult;
use App\DTO\ImportTile;
use App\Entity\HolidayBlacklistEntry;
use App\Entity\HolidayImportCandidate;
use App\Enum\BlacklistReason;
use App\Enum\HolidayDiffStatus;
use App\Enum\ImportCandidateStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * The import page's model: turns a pasted holiday list into persistent tiles, and works out on every
 * render where each tile stands against the holidays stored at that moment.
 *
 * Every "is this the same holiday" question - stored holiday, existing tile, blacklist entry - goes
 * through HolidayNameMatcher at MATCH_THRESHOLD, so "Dzień Kota" and "Światowy Dzień Kotów" are one
 * tile, one blacklist hit and one stored holiday alike. Exact-text comparison anywhere in here would
 * bring back exactly the duplicates the matcher exists to catch.
 *
 * Nothing here stores a holiday: "Add" on a tile leads to the regular create form, and the tile
 * resolves itself once the holiday exists.
 */
class HolidayImportService
{
	/** How many close stored names an ambiguous tile offers as "this is it". */
	public const int MAX_ALTERNATIVES = 3;

	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly HolidayDiffService     $diffService,
		private readonly HolidayTextParser      $parser,
	)
	{
	}

	public function import(string $text): ImportResult
	{
		$candidates = $this->diffService->candidates();
		$rows = $this->diffService->diff($this->parser->parse($text), $candidates);
		$blacklist = $this->blacklistWithTokens();

		/** @var list<array{item: HolidayImportCandidate, tokens: string[]}> $tiles */
		$tiles = [];
		foreach ($this->entityManager->getRepository(HolidayImportCandidate::class)->findAll() as $tile) {
			$tiles[] = ['item' => $tile, 'tokens' => HolidayNameMatcher::tokens($tile->name)];
		}

		$created = 0;
		$merged = 0;
		$alreadyStored = 0;
		$blacklisted = [];
		$unparsed = [];
		// A date counts once per paste: a source listing the same holiday twice is one source.
		$seenThisPaste = [];

		foreach ($rows as $row) {
			$parsed = $row->parsed;
			if ($row->status === HolidayDiffStatus::UNPARSED) {
				$unparsed[] = ['line' => $parsed->lineNumber, 'text' => $parsed->rawLine];
				continue;
			}
			if ($row->status === HolidayDiffStatus::EXACT || $row->status === HolidayDiffStatus::FLOATING_MATCH) {
				$alreadyStored++;
				continue;
			}

			$tokens = HolidayNameMatcher::tokens($parsed->name);
			$entry = self::bestMatch($tokens, $blacklist);
			if ($entry !== null) {
				$blacklisted[] = [
					'name' => $parsed->name,
					'rejected_as' => $entry->name,
					'reason' => $entry->reason->label(),
				];
				continue;
			}

			$tile = self::bestMatch($tokens, $tiles);
			if ($tile === null) {
				$tile = new HolidayImportCandidate($parsed->name, new DateTimeImmutable());
				$this->entityManager->persist($tile);
				$tiles[] = ['item' => $tile, 'tokens' => $tokens];
				$created++;
			} else {
				$merged++;
			}

			$seenKey = spl_object_id($tile) . '|' . HolidayImportCandidate::dateKey((int)$parsed->day, (int)$parsed->month);
			if (!isset($seenThisPaste[$seenKey])) {
				$seenThisPaste[$seenKey] = true;
				$tile->addDate((int)$parsed->day, (int)$parsed->month);
			}
		}

		$this->pruneResolved(array_column($tiles, 'item'), $candidates);
		$this->entityManager->flush();

		return new ImportResult($created, $merged, $alreadyStored, $blacklisted, $unparsed);
	}

	/**
	 * @return list<ImportTile> the tiles that still need a decision, in calendar order
	 */
	public function tiles(): array
	{
		$candidates = $this->diffService->candidates();
		$index = self::index($candidates);
		$tiles = [];
		foreach ($this->entityManager->getRepository(HolidayImportCandidate::class)->findAll() as $candidate) {
			$tile = $this->evaluate($candidate, $candidates, $index);
			if ($tile->status !== ImportCandidateStatus::RESOLVED) {
				$tiles[] = $tile;
			}
		}
		usort($tiles, function (ImportTile $left, ImportTile $right): int {
			$leftDate = $left->candidate->primaryDate() ?? ['month' => 13, 'day' => 32];
			$rightDate = $right->candidate->primaryDate() ?? ['month' => 13, 'day' => 32];
			$byDate = [$leftDate['month'], $leftDate['day']] <=> [$rightDate['month'], $rightDate['day']];
			if ($byDate !== 0) {
				return $byDate;
			}
			return strcmp($left->candidate->name, $right->candidate->name);
		});
		return $tiles;
	}

	public function tile(HolidayImportCandidate $candidate): ImportTile
	{
		$candidates = $this->diffService->candidates();
		return $this->evaluate($candidate, $candidates, self::index($candidates));
	}

	public function remove(HolidayImportCandidate $candidate): void
	{
		$this->entityManager->remove($candidate);
		$this->entityManager->flush();
	}

	/**
	 * @throws InvalidArgumentException when the reason needs a comment and none was given
	 */
	public function reject(HolidayImportCandidate $candidate, BlacklistReason $reason, ?string $comment): HolidayBlacklistEntry
	{
		$comment = trim((string)$comment);
		if ($comment === '') {
			$comment = null;
		}
		if ($comment === null && $reason->requiresComment()) {
			throw new InvalidArgumentException('Say why - "Other" needs a comment.');
		}
		if ($comment !== null && mb_strlen($comment) > 1023) {
			throw new InvalidArgumentException('The comment must be at most 1023 characters.');
		}

		$entry = new HolidayBlacklistEntry($candidate->name, $candidate->dates, $reason, $comment, new DateTimeImmutable());
		$this->entityManager->persist($entry);
		$this->entityManager->remove($candidate);
		$this->entityManager->flush();
		return $entry;
	}

	/**
	 * Ignores the date conflicts the tile shows right now - each as the pair of stored and source date,
	 * so a conflict with a date nobody has seen yet still comes back.
	 */
	public function ignoreConflicts(HolidayImportCandidate $candidate): void
	{
		$tile = $this->tile($candidate);
		if ($tile->status !== ImportCandidateStatus::DATE_MISMATCH || $tile->match === null) {
			throw new InvalidArgumentException('This tile has no date conflict to ignore.');
		}
		$pairs = [];
		foreach ($tile->conflictingDates as $date) {
			$pairs[] = HolidayImportCandidate::datePair((int)$tile->match->day, (int)$tile->match->month, $date['day'], $date['month']);
		}
		$candidate->ignorePairs($pairs);
		$this->entityManager->flush();
	}

	/**
	 * Records "this pasted name is that stored holiday", for names the matcher only found close.
	 */
	public function link(HolidayImportCandidate $candidate, string $kind, int $metadataId): void
	{
		$index = self::index($this->diffService->candidates());
		if (!isset($index[self::key($kind, $metadataId)])) {
			throw new InvalidArgumentException('That holiday does not exist.');
		}
		$candidate->link($kind, $metadataId);
		$this->entityManager->flush();
	}

	public function restore(HolidayBlacklistEntry $entry): void
	{
		$this->entityManager->remove($entry);
		$this->entityManager->flush();
	}

	/**
	 * @param HolidayCandidate[] $candidates
	 * @param array<string, HolidayCandidate> $index
	 */
	private function evaluate(HolidayImportCandidate $tile, array $candidates, array $index): ImportTile
	{
		$match = null;
		if ($tile->isLinked()) {
			// A link to a holiday deleted since falls back to matching by name.
			$match = $index[self::key((string)$tile->linkedKind, (int)$tile->linkedMetadataId)] ?? null;
		}

		if ($match === null) {
			$ranked = self::rank($tile, $candidates);
			$best = $ranked[0] ?? null;
			if ($best === null || $best['score'] < HolidayNameMatcher::CANDIDATE_THRESHOLD) {
				return new ImportTile($tile, ImportCandidateStatus::MISSING);
			}
			if ($best['score'] < HolidayNameMatcher::MATCH_THRESHOLD) {
				$alternatives = array_values(array_filter(
					array_slice($ranked, 0, self::MAX_ALTERNATIVES),
					fn(array $entry) => $entry['score'] >= HolidayNameMatcher::CANDIDATE_THRESHOLD
				));
				return new ImportTile($tile, ImportCandidateStatus::AMBIGUOUS, null, $alternatives);
			}
			$match = $best['candidate'];
		}

		if ($match->kind === 'floating') {
			// A floating holiday has no single date a source could contradict.
			return new ImportTile($tile, ImportCandidateStatus::RESOLVED, $match);
		}

		$conflicts = [];
		foreach ($tile->sortedDates() as $date) {
			if ($date['day'] === $match->day && $date['month'] === $match->month) {
				continue;
			}
			$pair = HolidayImportCandidate::datePair((int)$match->day, (int)$match->month, $date['day'], $date['month']);
			if (!$tile->isPairIgnored($pair)) {
				$conflicts[] = $date;
			}
		}
		if ($conflicts === []) {
			return new ImportTile($tile, ImportCandidateStatus::RESOLVED, $match);
		}
		return new ImportTile($tile, ImportCandidateStatus::DATE_MISMATCH, $match, [], $conflicts);
	}

	/**
	 * @param HolidayCandidate[] $candidates
	 * @return list<array{candidate: HolidayCandidate, score: float}> best first; on equal scores a
	 *         stored holiday on the tile's main date wins, so the order never depends on load order
	 */
	private static function rank(HolidayImportCandidate $tile, array $candidates): array
	{
		$tokens = HolidayNameMatcher::tokens($tile->name);
		$primary = $tile->primaryDate();
		$ranked = [];
		foreach ($candidates as $candidate) {
			$score = HolidayNameMatcher::scoreTokens($tokens, $candidate->tokens);
			if ($score <= 0.0) {
				continue;
			}
			$sameDate = $primary !== null && $candidate->day === $primary['day'] && $candidate->month === $primary['month'];
			$ranked[] = ['candidate' => $candidate, 'score' => $score, 'same_date' => $sameDate];
		}
		usort($ranked, function (array $left, array $right): int {
			if ($left['score'] !== $right['score']) {
				return $right['score'] <=> $left['score'];
			}
			return $right['same_date'] <=> $left['same_date'];
		});
		return array_map(fn(array $entry) => ['candidate' => $entry['candidate'], 'score' => $entry['score']], $ranked);
	}

	/**
	 * Deletes the tiles whose holiday is now stored with nothing left to decide, unless the row
	 * remembers a decision (an alias, an ignored conflict) the next paste of the same source needs.
	 *
	 * @param HolidayImportCandidate[] $tiles
	 * @param HolidayCandidate[] $candidates
	 */
	private function pruneResolved(array $tiles, array $candidates): void
	{
		$index = self::index($candidates);
		foreach ($tiles as $tile) {
			if ($tile->holdsDecision()) {
				continue;
			}
			if ($this->evaluate($tile, $candidates, $index)->status === ImportCandidateStatus::RESOLVED) {
				$this->entityManager->remove($tile);
			}
		}
	}

	/**
	 * @return list<array{item: HolidayBlacklistEntry, tokens: string[]}>
	 */
	private function blacklistWithTokens(): array
	{
		$entries = [];
		foreach ($this->entityManager->getRepository(HolidayBlacklistEntry::class)->findAll() as $entry) {
			$entries[] = ['item' => $entry, 'tokens' => HolidayNameMatcher::tokens($entry->name)];
		}
		return $entries;
	}

	/**
	 * The item whose name the matcher considers the same as `$tokens`, the closest one when several do.
	 *
	 * @template T of object
	 * @param string[] $tokens
	 * @param list<array{item: T, tokens: string[]}> $items
	 * @return T|null
	 */
	private static function bestMatch(array $tokens, array $items): ?object
	{
		$best = null;
		$bestScore = 0.0;
		foreach ($items as $item) {
			$score = HolidayNameMatcher::scoreTokens($tokens, $item['tokens']);
			if ($score >= HolidayNameMatcher::MATCH_THRESHOLD && $score > $bestScore) {
				$bestScore = $score;
				$best = $item['item'];
			}
		}
		return $best;
	}

	/**
	 * @param HolidayCandidate[] $candidates
	 * @return array<string, HolidayCandidate>
	 */
	private static function index(array $candidates): array
	{
		$index = [];
		foreach ($candidates as $candidate) {
			$index[self::key($candidate->kind, $candidate->metadataId)] = $candidate;
		}
		return $index;
	}

	private static function key(string $kind, int $metadataId): string
	{
		return $kind . ':' . $metadataId;
	}
}
