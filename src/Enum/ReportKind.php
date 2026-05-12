<?php

namespace App\Enum;

use App\Entity\FixedHolidayError;
use App\Entity\FixedHolidayMetadata;
use App\Entity\FixedHolidaySuggestion;
use App\Entity\FloatingHolidayError;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\FloatingHolidaySuggestion;

enum ReportKind: string
{
	case FIXED_SUGGESTION = 'fixed_suggestion';
	case FLOATING_SUGGESTION = 'floating_suggestion';
	case FIXED_ERROR = 'fixed_error';
	case FLOATING_ERROR = 'floating_error';

	/** @return class-string */
	public function entityClass(): string
	{
		return match ($this) {
			self::FIXED_SUGGESTION => FixedHolidaySuggestion::class,
			self::FLOATING_SUGGESTION => FloatingHolidaySuggestion::class,
			self::FIXED_ERROR => FixedHolidayError::class,
			self::FLOATING_ERROR => FloatingHolidayError::class,
		};
	}

	/** @return class-string */
	public function metadataClass(): string
	{
		return match ($this) {
			self::FIXED_SUGGESTION, self::FIXED_ERROR => FixedHolidayMetadata::class,
			self::FLOATING_SUGGESTION, self::FLOATING_ERROR => FloatingHolidayMetadata::class,
		};
	}

	public function isSuggestion(): bool
	{
		return $this === self::FIXED_SUGGESTION || $this === self::FLOATING_SUGGESTION;
	}
}
