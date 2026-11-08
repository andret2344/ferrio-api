<?php

namespace App\DTO;

use App\Entity\HolidayImportCandidate;
use App\Enum\ImportCandidateStatus;

/**
 * An import tile as the page renders it: the stored row plus its standing against the database now.
 */
readonly class ImportTile
{
	/**
	 * @param HolidayCandidate|null $match the stored holiday it is (DATE_MISMATCH, RESOLVED)
	 * @param list<array{candidate: HolidayCandidate, score: float}> $alternatives close stored names (AMBIGUOUS)
	 * @param list<array{day: int, month: int, count: int}> $conflictingDates source dates the stored one contradicts
	 */
	public function __construct(
		public HolidayImportCandidate $candidate,
		public ImportCandidateStatus  $status,
		public ?HolidayCandidate      $match = null,
		public array                  $alternatives = [],
		public array                  $conflictingDates = []
	)
	{
	}
}
