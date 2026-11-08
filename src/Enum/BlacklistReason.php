<?php

namespace App\Enum;

/**
 * Why a pasted holiday was rejected. A fixed list rather than free text, so the blacklist stays
 * filterable a year from now; OTHER exists for the cases the list does not cover and is the one
 * reason that cannot stand without a comment - "other" alone records nothing.
 */
enum BlacklistReason: string
{
	case INVENTED = 'invented';
	case DUPLICATE = 'duplicate';
	case OUT_OF_SCOPE = 'out_of_scope';
	case OTHER = 'other';

	public function label(): string
	{
		return match ($this) {
			self::INVENTED => 'Invented - no real origin',
			self::DUPLICATE => 'Duplicate of a stored holiday',
			self::OUT_OF_SCOPE => 'Real, but not for Ferrio',
			self::OTHER => 'Other',
		};
	}

	public function requiresComment(): bool
	{
		return $this === self::OTHER;
	}
}
