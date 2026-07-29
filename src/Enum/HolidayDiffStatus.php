<?php

namespace App\Enum;

/**
 * The verdict for one line of a pasted holiday list compared against the database.
 *
 * Deliberately more than the three buckets a human would name (match / different date / missing):
 * a name can match a *floating* holiday, where "different date" is meaningless, and a line can fail
 * to parse at all. Both get their own bucket instead of being folded into MISSING, because folding
 * them there would invite adding a duplicate.
 */
enum HolidayDiffStatus: string
{
	case EXACT = 'exact';
	case DATE_MISMATCH = 'date_mismatch';
	case FLOATING_MATCH = 'floating_match';
	case AMBIGUOUS = 'ambiguous';
	case MISSING = 'missing';
	case UNPARSED = 'unparsed';

	public function label(): string
	{
		return match ($this) {
			self::EXACT => 'Already stored, same date',
			self::DATE_MISMATCH => 'Already stored, different date',
			self::FLOATING_MATCH => 'Matches a floating holiday - compare by hand',
			self::AMBIGUOUS => 'Close match - decide by hand',
			self::MISSING => 'Not in the database',
			self::UNPARSED => 'Looks like a date, could not be read',
		};
	}
}
