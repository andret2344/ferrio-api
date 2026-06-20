<?php

namespace App\Entity;

enum Platform: string
{
	case ANDROID = 'android';
	case IOS = 'ios';
	case WEB = 'web';
	case API = 'api';
	case UNKNOWN = 'unknown';

	/**
	 * @return string[]
	 */
	public static function values(): array
	{
		return array_column(self::cases(), 'value');
	}

	public static function fromInputOrUnknown(?string $value): self
	{
		if ($value === null) {
			return self::UNKNOWN;
		}
		return self::tryFrom(strtolower($value)) ?? self::UNKNOWN;
	}
}
