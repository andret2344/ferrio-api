<?php

namespace App\DTO;

/**
 * A Polish holiday name already in the database, with its match tokens precomputed.
 *
 * The tokens are cached on the object because the diff is an N x M comparison (pasted lines x
 * stored holidays) and re-tokenising a stored name for every pasted line is the one thing that
 * would make the command slow.
 *
 * `day` / `month` are null for floating holidays, whose date is computed per year.
 */
readonly class HolidayCandidate
{
	/**
	 * @param int $metadataId
	 * @param 'fixed'|'floating' $kind
	 * @param string $name
	 * @param string[] $tokens
	 * @param int|null $day
	 * @param int|null $month
	 */
	public function __construct(
		public int    $metadataId,
		public string $kind,
		public string $name,
		public array  $tokens,
		public ?int   $day = null,
		public ?int   $month = null
	)
	{
	}
}
