<?php

namespace App\Enum;

/**
 * Where an import tile stands against the holidays stored right now. Computed on every render, never
 * stored: a tile has to disappear the moment its holiday is added, whichever page added it.
 */
enum ImportCandidateStatus: string
{
	/** Nothing stored resembles the name. */
	case MISSING = 'missing';

	/** A stored name is close, but not close enough to call it the same holiday. */
	case AMBIGUOUS = 'ambiguous';

	/** The holiday is stored, and a source gave it a date the database does not have. */
	case DATE_MISMATCH = 'date_mismatch';

	/** The holiday is stored and nothing about it is left to decide; the tile is hidden. */
	case RESOLVED = 'resolved';

	public function label(): string
	{
		return match ($this) {
			self::MISSING => 'Not stored',
			self::AMBIGUOUS => 'Close match',
			self::DATE_MISMATCH => 'Different date',
			self::RESOLVED => 'Stored',
		};
	}
}
