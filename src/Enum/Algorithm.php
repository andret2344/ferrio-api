<?php

namespace App\Enum;

use App\Service\Algorithm\FirstDayOfWeekAfterDateResolver;
use App\Service\Algorithm\HardcodedDatesResolver;
use App\Service\Algorithm\LastDayOfWeekBeforeDateResolver;
use App\Service\Algorithm\LastNthDayOfWeekInMonthResolver;
use App\Service\Algorithm\LeapYearDateResolver;
use App\Service\Algorithm\NthDayOfWeekInMonthResolver;
use App\Service\Algorithm\NthDayThenNextDayOfWeekResolver;

enum Algorithm: string
{
	case NTH_DAY_OF_WEEK_IN_MONTH = 'nth_day_of_week_in_month';
	case LAST_NTH_DAY_OF_WEEK_IN_MONTH = 'last_nth_day_of_week_in_month';
	case FIRST_DAY_OF_WEEK_AFTER_DATE = 'first_day_of_week_after_date';
	case LAST_DAY_OF_WEEK_BEFORE_DATE = 'last_day_of_week_before_date';
	case NTH_DAY_THEN_NEXT_DAY_OF_WEEK = 'nth_day_then_next_day_of_week';
	case LEAP_YEAR_DATE = 'leap_year_date';
	case HARDCODED_DATES = 'hardcoded_dates';

	/**
	 * @return class-string<\App\Service\Algorithm\AlgorithmResolverInterface>
	 */
	public function resolverClass(): string
	{
		return match ($this) {
			self::NTH_DAY_OF_WEEK_IN_MONTH => NthDayOfWeekInMonthResolver::class,
			self::LAST_NTH_DAY_OF_WEEK_IN_MONTH => LastNthDayOfWeekInMonthResolver::class,
			self::FIRST_DAY_OF_WEEK_AFTER_DATE => FirstDayOfWeekAfterDateResolver::class,
			self::LAST_DAY_OF_WEEK_BEFORE_DATE => LastDayOfWeekBeforeDateResolver::class,
			self::NTH_DAY_THEN_NEXT_DAY_OF_WEEK => NthDayThenNextDayOfWeekResolver::class,
			self::LEAP_YEAR_DATE => LeapYearDateResolver::class,
			self::HARDCODED_DATES => HardcodedDatesResolver::class,
		};
	}
}
