<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * One tile on the import page: a holiday name a pasted source mentioned that is not settled yet.
 *
 * A tile is keyed by its name *as the matcher sees it*, not by the exact text: pasting "Dzień Kota"
 * after "Światowy Dzień Kotów" adds to the same tile. Every date the sources gave is kept with the
 * number of pastes that gave it, because sources disagreeing on the date is exactly what the admin
 * needs to see - collapsing them into one date would hide it.
 *
 * Whether the tile still needs attention is not stored here (see ImportCandidateStatus): it is
 * recomputed against the stored holidays on every render, so adding the holiday anywhere in the
 * panel makes the tile go away on its own.
 */
#[ORM\Entity]
#[ORM\Table(name: 'holiday_import_candidate')]
class HolidayImportCandidate
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer')]
	private(set) int $id;

	#[ORM\Column(length: 255)]
	private(set) string $name;

	/** @var array<string, int> "day.month" => how many pastes gave that date */
	#[ORM\Column(type: 'json')]
	private(set) array $dates = [];

	/**
	 * Date conflicts the admin chose to ignore, as "storedDay.storedMonth>sourceDay.sourceMonth".
	 * Stored as the pair rather than the source date alone, so a conflict comes back when either side
	 * changes - a third date from a new source, or the stored date being edited, is new information.
	 *
	 * @var list<string>
	 */
	#[ORM\Column(type: 'json')]
	private(set) array $ignoredDatePairs = [];

	/** 'fixed' | 'floating' - set when the admin said "this is that stored holiday". */
	#[ORM\Column(length: 8, nullable: true)]
	private(set) ?string $linkedKind = null;

	#[ORM\Column(nullable: true)]
	private(set) ?int $linkedMetadataId = null;

	/** @var array<string, mixed>|null the last web check, see HolidayVerifier */
	#[ORM\Column(type: 'json', nullable: true)]
	private(set) ?array $verification = null;

	#[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
	private(set) ?DateTimeImmutable $verifiedAt = null;

	#[ORM\Column(type: 'datetimetz_immutable')]
	private(set) DateTimeImmutable $createdAt;

	public function __construct(string $name, DateTimeImmutable $createdAt)
	{
		$this->name = $name;
		$this->createdAt = $createdAt;
	}

	public static function dateKey(int $day, int $month): string
	{
		return sprintf('%d.%d', $day, $month);
	}

	/**
	 * @return array{0: int, 1: int} [day, month]
	 */
	public static function parseDateKey(string $key): array
	{
		[$day, $month] = explode('.', $key);
		return [(int)$day, (int)$month];
	}

	public function addDate(int $day, int $month): void
	{
		$key = self::dateKey($day, $month);
		$this->dates[$key] = ($this->dates[$key] ?? 0) + 1;
	}

	/**
	 * @return list<array{day: int, month: int, count: int}> most often given first, then by calendar
	 */
	public function sortedDates(): array
	{
		$dates = [];
		foreach ($this->dates as $key => $count) {
			[$day, $month] = self::parseDateKey((string)$key);
			$dates[] = ['day' => $day, 'month' => $month, 'count' => (int)$count];
		}
		usort($dates, function (array $left, array $right): int {
			if ($left['count'] !== $right['count']) {
				return $right['count'] <=> $left['count'];
			}
			return [$left['month'], $left['day']] <=> [$right['month'], $right['day']];
		});
		return $dates;
	}

	/**
	 * @return array{day: int, month: int, count: int}|null the date the sources agree on most
	 */
	public function primaryDate(): ?array
	{
		return $this->sortedDates()[0] ?? null;
	}

	public static function datePair(int $storedDay, int $storedMonth, int $sourceDay, int $sourceMonth): string
	{
		return sprintf('%s>%s', self::dateKey($storedDay, $storedMonth), self::dateKey($sourceDay, $sourceMonth));
	}

	public function isPairIgnored(string $pair): bool
	{
		return in_array($pair, $this->ignoredDatePairs, true);
	}

	/**
	 * @param list<string> $pairs
	 */
	public function ignorePairs(array $pairs): void
	{
		$this->ignoredDatePairs = array_values(array_unique([...$this->ignoredDatePairs, ...$pairs]));
	}

	public function link(string $kind, int $metadataId): void
	{
		$this->linkedKind = $kind;
		$this->linkedMetadataId = $metadataId;
	}

	public function isLinked(): bool
	{
		return $this->linkedKind !== null && $this->linkedMetadataId !== null;
	}

	/**
	 * Whether the tile carries a decision that would be lost if the row were pruned once resolved:
	 * a remembered alias or an ignored date conflict must survive the next paste of the same source.
	 */
	public function holdsDecision(): bool
	{
		return $this->isLinked() || $this->ignoredDatePairs !== [];
	}

	/**
	 * @param array<string, mixed> $verification
	 */
	public function verify(array $verification, DateTimeImmutable $verifiedAt): void
	{
		$this->verification = $verification;
		$this->verifiedAt = $verifiedAt;
	}
}
