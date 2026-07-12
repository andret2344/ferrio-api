<?php

namespace App\Enum;

enum ApiHitGrouping: string
{
	case HOUR = 'hour';
	case DAY = 'day';
	case WEEK = 'week';
	case MONTH = 'month';

	/**
	 * Bucket label format applied to the row's UTC `bucket_hour`. Formats are chosen so that a
	 * plain string sort equals a chronological sort.
	 */
	public function format(): string
	{
		return match ($this) {
			self::HOUR => 'Y-m-d H:00',
			self::DAY => 'Y-m-d',
			self::WEEK => 'o-\WW',
			self::MONTH => 'Y-m',
		};
	}
}
