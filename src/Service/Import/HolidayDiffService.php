<?php

namespace App\Service\Import;

use App\DTO\HolidayCandidate;
use App\DTO\HolidayDiffRow;
use App\DTO\ParsedHoliday;
use App\Entity\FixedHoliday;
use App\Entity\FloatingHoliday;
use App\Entity\Language;
use App\Enum\HolidayDiffStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Compares parsed holiday lines against the Polish names already stored, and classifies each one.
 *
 * Read-only by design: it reports, it never writes. The sources this feeds on are blogs and forums,
 * which carry typos, duplicates and invented holidays, so the decision to store a row stays manual.
 *
 * Floating holidays are loaded into the same candidate pool as fixed ones. Skipping them would make
 * every floating holiday the source happened to pin to a concrete date ("Dzień Matki - 26 maja")
 * come back as MISSING, and acting on that would create a duplicate.
 */
class HolidayDiffService
{
	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	/**
	 * @param ParsedHoliday[] $parsed
	 * @return HolidayDiffRow[]
	 */
	public function diff(array $parsed): array
	{
		$candidates = $this->candidates();
		$rows = [];
		foreach ($parsed as $holiday) {
			$rows[] = $this->classify($holiday, $candidates);
		}
		return $rows;
	}

	/**
	 * @param HolidayCandidate[] $candidates
	 */
	private function classify(ParsedHoliday $holiday, array $candidates): HolidayDiffRow
	{
		if (!$holiday->parsed) {
			return new HolidayDiffRow($holiday, HolidayDiffStatus::UNPARSED);
		}
		$tokens = HolidayNameMatcher::tokens($holiday->name);
		$best = null;
		$bestScore = 0.0;
		foreach ($candidates as $candidate) {
			$score = HolidayNameMatcher::scoreTokens($tokens, $candidate->tokens);
			if ($score < $bestScore) {
				continue;
			}
			if ($score > $bestScore) {
				$bestScore = $score;
				$best = $candidate;
				continue;
			}
			// Equal scores: a stored holiday whose date also agrees is the better answer, otherwise
			// two identically-named rows would make the verdict depend on load order.
			if ($best !== null && !$this->dateMatches($best, $holiday) && $this->dateMatches($candidate, $holiday)) {
				$best = $candidate;
			}
		}
		if ($best === null || $bestScore < HolidayNameMatcher::CANDIDATE_THRESHOLD) {
			return new HolidayDiffRow($holiday, HolidayDiffStatus::MISSING, null, $bestScore);
		}
		if ($bestScore < HolidayNameMatcher::MATCH_THRESHOLD) {
			return new HolidayDiffRow($holiday, HolidayDiffStatus::AMBIGUOUS, $best, $bestScore);
		}
		if ($best->kind === 'floating') {
			return new HolidayDiffRow($holiday, HolidayDiffStatus::FLOATING_MATCH, $best, $bestScore);
		}
		if ($this->dateMatches($best, $holiday)) {
			return new HolidayDiffRow($holiday, HolidayDiffStatus::EXACT, $best, $bestScore);
		}
		return new HolidayDiffRow($holiday, HolidayDiffStatus::DATE_MISMATCH, $best, $bestScore);
	}

	private function dateMatches(HolidayCandidate $candidate, ParsedHoliday $holiday): bool
	{
		if ($candidate->kind !== 'fixed') {
			return false;
		}
		return $candidate->day === $holiday->day && $candidate->month === $holiday->month;
	}

	/**
	 * @return HolidayCandidate[] every Polish holiday name, fixed first
	 */
	public function candidates(): array
	{
		$candidates = [];
		$fixed = $this->entityManager
			->createQuery(
				'SELECT m.id AS id, m.day AS day, m.month AS month, h.name AS name
				FROM ' . FixedHoliday::class . ' h
				JOIN h.metadata m
				WHERE h.language = :language'
			)
			->setParameter('language', Language::DEFAULT_CODE)
			->getArrayResult();
		foreach ($fixed as $row) {
			$candidates[] = new HolidayCandidate(
				(int)$row['id'],
				'fixed',
				$row['name'],
				HolidayNameMatcher::tokens($row['name']),
				(int)$row['day'],
				(int)$row['month']
			);
		}

		$floating = $this->entityManager
			->createQuery(
				'SELECT m.id AS id, h.name AS name
				FROM ' . FloatingHoliday::class . ' h
				JOIN h.metadata m
				WHERE h.language = :language'
			)
			->setParameter('language', Language::DEFAULT_CODE)
			->getArrayResult();
		foreach ($floating as $row) {
			$candidates[] = new HolidayCandidate(
				(int)$row['id'],
				'floating',
				$row['name'],
				HolidayNameMatcher::tokens($row['name'])
			);
		}

		return $candidates;
	}
}
