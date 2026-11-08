<?php

namespace App\DTO;

/**
 * What one paste did to the import page, shown once above the tiles.
 */
readonly class ImportResult
{
	/**
	 * @param int $created new tiles
	 * @param int $merged lines that landed on an existing tile (a repeat, or the same holiday reworded)
	 * @param int $alreadyStored lines whose holiday is stored with the same date, or as a floating one
	 * @param list<array{name: string, rejected_as: string, reason: string}> $blacklisted skipped lines
	 * @param list<array{line: int, text: string}> $unparsed lines that read as a date but are not one
	 */
	public function __construct(
		public int   $created,
		public int   $merged,
		public int   $alreadyStored,
		public array $blacklisted,
		public array $unparsed
	)
	{
	}

	/**
	 * @return array<string, mixed> snake_case, for the session flash the page renders from
	 */
	public function toArray(): array
	{
		return [
			'created' => $this->created,
			'merged' => $this->merged,
			'already_stored' => $this->alreadyStored,
			'blacklisted' => $this->blacklisted,
			'unparsed' => $this->unparsed,
		];
	}
}
