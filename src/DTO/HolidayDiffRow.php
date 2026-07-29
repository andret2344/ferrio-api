<?php

namespace App\DTO;

use App\Enum\HolidayDiffStatus;

/**
 * One pasted line paired with the verdict and, when there was one, the closest stored holiday.
 */
readonly class HolidayDiffRow
{
	public function __construct(
		public ParsedHoliday     $parsed,
		public HolidayDiffStatus $status,
		public ?HolidayCandidate $candidate = null,
		public float             $score = 0.0
	)
	{
	}
}
