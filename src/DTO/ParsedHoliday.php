<?php

namespace App\DTO;

/**
 * One line of pasted text after parsing.
 *
 * `day` and `month` are null when the line looked date-shaped but could not be read - the line is
 * then reported as unparsed rather than guessed at, because a guessed date silently turns into a
 * wrong "missing holiday" further down the pipeline.
 */
class ParsedHoliday
{
	public bool $parsed {
		get => $this->day !== null && $this->month !== null;
	}

	public function __construct(
		public readonly int $lineNumber,
		public readonly string $rawLine,
		public readonly string $name,
		public readonly ?int $day = null,
		public readonly ?int $month = null
	) {
	}
}
