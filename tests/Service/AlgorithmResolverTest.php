<?php

namespace App\Tests\Service;

use App\Service\Algorithm\FixedDateWithChangesResolver;
use App\Service\Algorithm\EarthHourResolver;
use App\Service\Algorithm\FirstDayOfWeekAfterDateResolver;
use App\Service\Algorithm\HardcodedDatesResolver;
use App\Service\Algorithm\LastDayOfWeekBeforeDateResolver;
use App\Service\Algorithm\LastNthDayOfWeekInMonthResolver;
use App\Service\Algorithm\LeapYearDateResolver;
use App\Service\Algorithm\NthDayOfWeekInMonthResolver;
use App\Service\Algorithm\NthDayThenNextDayOfWeekResolver;
use PHPUnit\Framework\TestCase;

class AlgorithmResolverTest extends TestCase
{
	public function testNthDayOfWeekInMonth(): void
	{
		$resolver = new NthDayOfWeekInMonthResolver();

		// 2nd Monday of September 2026 → September 14
		$result = $resolver->calculate([
			'nth' => 2,
			'dayOfWeek' => 1, // Monday
			'month' => 9,
		], 2026);

		$this->assertSame(14, $result['day']);
		$this->assertSame(9, $result['month']);
	}

	public function testNthDayOfWeekInMonthFirstMonday(): void
	{
		$resolver = new NthDayOfWeekInMonthResolver();

		// 1st Monday of September 2026 → September 7
		$result = $resolver->calculate([
			'nth' => 1,
			'dayOfWeek' => 1,
			'month' => 9,
		], 2026);

		$this->assertSame(7, $result['day']);
		$this->assertSame(9, $result['month']);
	}

	public function testNthDayOfWeekInMonthThirdThursday(): void
	{
		$resolver = new NthDayOfWeekInMonthResolver();

		// 3rd Thursday of November 2026 → November 19
		$result = $resolver->calculate([
			'nth' => 3,
			'dayOfWeek' => 4, // Thursday
			'month' => 11,
		], 2026);

		$this->assertSame(19, $result['day']);
		$this->assertSame(11, $result['month']);
	}

	public function testLastNthDayOfWeekInMonth(): void
	{
		$resolver = new LastNthDayOfWeekInMonthResolver();

		// Last Monday of May 2026 → May 25
		$result = $resolver->calculate([
			'nth' => 1,
			'dayOfWeek' => 1, // Monday
			'month' => 5,
		], 2026);

		$this->assertSame(25, $result['day']);
		$this->assertSame(5, $result['month']);
	}

	public function testLastNthDayOfWeekInMonthSecondToLast(): void
	{
		$resolver = new LastNthDayOfWeekInMonthResolver();

		// 2nd-to-last Friday of March 2026 → March 20
		$result = $resolver->calculate([
			'nth' => 2,
			'dayOfWeek' => 5, // Friday
			'month' => 3,
		], 2026);

		$this->assertSame(20, $result['day']);
		$this->assertSame(3, $result['month']);
	}

	public function testFirstDayOfWeekAfterDateInclusive(): void
	{
		$resolver = new FirstDayOfWeekAfterDateResolver();

		// First Monday on or after March 1, 2026 (March 1 is Sunday) → March 2
		$result = $resolver->calculate([
			'dayOfWeek' => 1, // Monday
			'month' => 3,
			'day' => 1,
			'inclusive' => true,
		], 2026);

		$this->assertSame(2, $result['day']);
		$this->assertSame(3, $result['month']);
	}

	public function testFirstDayOfWeekAfterDateWhenDateIsSameDay(): void
	{
		$resolver = new FirstDayOfWeekAfterDateResolver();

		// First Sunday on or after March 1, 2026 (March 1 is Sunday) → March 1 (inclusive)
		$result = $resolver->calculate([
			'dayOfWeek' => 7, // Sunday
			'month' => 3,
			'day' => 1,
			'inclusive' => true,
		], 2026);

		$this->assertSame(1, $result['day']);
		$this->assertSame(3, $result['month']);
	}

	public function testFirstDayOfWeekAfterDateNotInclusive(): void
	{
		$resolver = new FirstDayOfWeekAfterDateResolver();

		// First Sunday after March 1, 2026 (March 1 is Sunday, not inclusive) → March 8
		$result = $resolver->calculate([
			'dayOfWeek' => 7, // Sunday
			'month' => 3,
			'day' => 1,
			'inclusive' => false,
		], 2026);

		$this->assertSame(8, $result['day']);
		$this->assertSame(3, $result['month']);
	}

	public function testLastDayOfWeekBeforeDateInclusive(): void
	{
		$resolver = new LastDayOfWeekBeforeDateResolver();

		// Last Friday on or before March 1, 2026 (March 1 is Sunday) → February 27
		$result = $resolver->calculate([
			'dayOfWeek' => 5, // Friday
			'month' => 3,
			'day' => 1,
			'inclusive' => true,
		], 2026);

		$this->assertSame(27, $result['day']);
		$this->assertSame(2, $result['month']);
	}

	public function testLastDayOfWeekBeforeDateNotInclusive(): void
	{
		$resolver = new LastDayOfWeekBeforeDateResolver();

		// Last Sunday before March 1, 2026 (March 1 is Sunday, not inclusive) → February 22
		$result = $resolver->calculate([
			'dayOfWeek' => 7, // Sunday
			'month' => 3,
			'day' => 1,
			'inclusive' => false,
		], 2026);

		$this->assertSame(22, $result['day']);
		$this->assertSame(2, $result['month']);
	}

	public function testNthDayThenNextDayOfWeek(): void
	{
		$resolver = new NthDayThenNextDayOfWeekResolver(new NthDayOfWeekInMonthResolver());

		// 2nd Monday of September 2026 (Sep 14), then next Wednesday → Sep 16
		$result = $resolver->calculate([
			'nth' => 2,
			'dayOfWeek' => 1, // Monday
			'month' => 9,
			'afterDayOfWeek' => 3, // Wednesday
		], 2026);

		$this->assertSame(16, $result['day']);
		$this->assertSame(9, $result['month']);
	}

	public function testLeapYearDateLeapYear(): void
	{
		$resolver = new LeapYearDateResolver();

		$result = $resolver->calculate([
			'leapDay' => 29,
			'leapMonth' => 2,
			'nonLeapDay' => 1,
			'nonLeapMonth' => 3,
		], 2024);

		$this->assertSame(29, $result['day']);
		$this->assertSame(2, $result['month']);
	}

	public function testLeapYearDateNonLeapYear(): void
	{
		$resolver = new LeapYearDateResolver();

		$result = $resolver->calculate([
			'leapDay' => 29,
			'leapMonth' => 2,
			'nonLeapDay' => 1,
			'nonLeapMonth' => 3,
		], 2026);

		$this->assertSame(1, $result['day']);
		$this->assertSame(3, $result['month']);
	}

	public function testHardcodedDates(): void
	{
		$resolver = new HardcodedDatesResolver();

		$result = $resolver->calculate([
			'2025' => '14.4',
			'2026' => '15.4',
		], 2026);

		$this->assertSame(15, $result['day']);
		$this->assertSame(4, $result['month']);
	}

	public function testHardcodedDatesMissingYearReturnsNull(): void
	{
		$resolver = new HardcodedDatesResolver();

		$result = $resolver->calculate([
			'2025' => '14.4',
		], 2030);

		$this->assertNull($result);
	}

	public function testNthDayOfWeekInMonthFourthThursdayNovember(): void
	{
		$resolver = new NthDayOfWeekInMonthResolver();

		// Thanksgiving 2026 — 4th Thursday of November
		$result = $resolver->calculate([
			'nth' => 4,
			'dayOfWeek' => 4,
			'month' => 11,
		], 2026);

		$this->assertSame(26, $result['day']);
		$this->assertSame(11, $result['month']);
	}

	public function testNthDayOfWeekInMonthWhenFirstDayIsTarget(): void
	{
		$resolver = new NthDayOfWeekInMonthResolver();

		// 1st Thursday of January 2026 (Jan 1 is Thursday)
		$result = $resolver->calculate([
			'nth' => 1,
			'dayOfWeek' => 4,
			'month' => 1,
		], 2026);

		$this->assertSame(1, $result['day']);
		$this->assertSame(1, $result['month']);
	}

	public function testLastNthDayOfWeekInMonthLastFridayDecember(): void
	{
		$resolver = new LastNthDayOfWeekInMonthResolver();

		// Last Friday of December 2026 (Dec 31 is Thursday → last Friday is Dec 25)
		$result = $resolver->calculate([
			'nth' => 1,
			'dayOfWeek' => 5,
			'month' => 12,
		], 2026);

		$this->assertSame(25, $result['day']);
		$this->assertSame(12, $result['month']);
	}

	public function testFirstDayOfWeekAfterDateCrossesMonthBoundary(): void
	{
		$resolver = new FirstDayOfWeekAfterDateResolver();

		// First Monday on or after Jan 30, 2026 (Friday) → Feb 2
		$result = $resolver->calculate([
			'dayOfWeek' => 1,
			'month' => 1,
			'day' => 30,
		], 2026);

		$this->assertSame(2, $result['day']);
		$this->assertSame(2, $result['month']);
	}

	public function testLastDayOfWeekBeforeDateCrossesMonthBoundary(): void
	{
		$resolver = new LastDayOfWeekBeforeDateResolver();

		// Last Friday on or before March 3, 2026 (Tuesday) → Feb 27
		$result = $resolver->calculate([
			'dayOfWeek' => 5,
			'month' => 3,
			'day' => 3,
		], 2026);

		$this->assertSame(27, $result['day']);
		$this->assertSame(2, $result['month']);
	}

	public function testNthDayThenNextDayOfWeekTuesdayAfterFirstMonday(): void
	{
		$resolver = new NthDayThenNextDayOfWeekResolver(new NthDayOfWeekInMonthResolver());

		// Tuesday after 1st Monday of July 2026 (July 6) → July 7
		$result = $resolver->calculate([
			'nth' => 1,
			'dayOfWeek' => 1,
			'month' => 7,
			'afterDayOfWeek' => 2,
		], 2026);

		$this->assertSame(7, $result['day']);
		$this->assertSame(7, $result['month']);
	}

	public function testLeapYearDateCenturyLeapYear(): void
	{
		$resolver = new LeapYearDateResolver();

		// 2000 is a leap year (divisible by 400)
		$result = $resolver->calculate([
			'leapDay' => 29,
			'leapMonth' => 2,
			'nonLeapDay' => 1,
			'nonLeapMonth' => 3,
		], 2000);

		$this->assertSame(29, $result['day']);
		$this->assertSame(2, $result['month']);
	}

	public function testLeapYearDateCenturyNonLeapYear(): void
	{
		$resolver = new LeapYearDateResolver();

		// 1900 is NOT a leap year (divisible by 100 but not 400)
		$result = $resolver->calculate([
			'leapDay' => 29,
			'leapMonth' => 2,
			'nonLeapDay' => 1,
			'nonLeapMonth' => 3,
		], 1900);

		$this->assertSame(1, $result['day']);
		$this->assertSame(3, $result['month']);
	}

	public function testHardcodedDatesEmptyArgsReturnsNull(): void
	{
		$resolver = new HardcodedDatesResolver();

		$result = $resolver->calculate([], 2026);

		$this->assertNull($result);
	}

	public function testEarthHourNormalYear(): void
	{
		$resolver = new EarthHourResolver();

		// 2026: Easter Apr 5, Holy Saturday Apr 4, last Sat of March = Mar 28 → no clash
		$result = $resolver->calculate([], 2026);

		$this->assertSame(28, $result['day']);
		$this->assertSame(3, $result['month']);
	}

	public function testEarthHourClashWithHolySaturday(): void
	{
		$resolver = new EarthHourResolver();

		// 2024: Easter Mar 31, Holy Saturday Mar 30, last Sat of March = Mar 30 → clash, shift to Mar 23
		$result = $resolver->calculate([], 2024);

		$this->assertSame(23, $result['day']);
		$this->assertSame(3, $result['month']);
	}

	public function testEarthHourClash2027(): void
	{
		$resolver = new EarthHourResolver();

		// 2027: Easter Mar 28, Holy Saturday Mar 27, last Sat of March = Mar 27 → clash, shift to Mar 20
		$result = $resolver->calculate([], 2027);

		$this->assertSame(20, $result['day']);
		$this->assertSame(3, $result['month']);
	}

	public function testEarthHourNoClash2025(): void
	{
		$resolver = new EarthHourResolver();

		// 2025: Easter Apr 20, last Sat of March = Mar 29 → no clash
		$result = $resolver->calculate([], 2025);

		$this->assertSame(29, $result['day']);
		$this->assertSame(3, $result['month']);
	}

	public function testDateChangedFromYearBeforeAnyChange(): void
	{
		$resolver = new FixedDateWithChangesResolver();

		// World Table Tennis Day before 2023 → April 6
		$result = $resolver->calculate([
			'defaultDay' => 6,
			'defaultMonth' => 4,
			'changes' => [
				['fromYear' => 2023, 'day' => 23, 'month' => 4],
			],
		], 2022);

		$this->assertSame(6, $result['day']);
		$this->assertSame(4, $result['month']);
	}

	public function testDateChangedFromYearAtChangeYear(): void
	{
		$resolver = new FixedDateWithChangesResolver();

		// World Table Tennis Day from 2023 → April 23
		$result = $resolver->calculate([
			'defaultDay' => 6,
			'defaultMonth' => 4,
			'changes' => [
				['fromYear' => 2023, 'day' => 23, 'month' => 4],
			],
		], 2023);

		$this->assertSame(23, $result['day']);
		$this->assertSame(4, $result['month']);
	}

	public function testDateChangedFromYearAfterChange(): void
	{
		$resolver = new FixedDateWithChangesResolver();

		// World Table Tennis Day after 2023 → April 23
		$result = $resolver->calculate([
			'defaultDay' => 6,
			'defaultMonth' => 4,
			'changes' => [
				['fromYear' => 2023, 'day' => 23, 'month' => 4],
			],
		], 2026);

		$this->assertSame(23, $result['day']);
		$this->assertSame(4, $result['month']);
	}

	public function testDateChangedFromYearMultipleChanges(): void
	{
		$resolver = new FixedDateWithChangesResolver();

		$args = [
			'defaultDay' => 1,
			'defaultMonth' => 3,
			'changes' => [
				['fromYear' => 2010, 'day' => 15, 'month' => 3],
				['fromYear' => 2020, 'day' => 22, 'month' => 6],
			],
		];

		// Before first change → default
		$result = $resolver->calculate($args, 2005);
		$this->assertSame(1, $result['day']);
		$this->assertSame(3, $result['month']);

		// Between first and second change → first change
		$result = $resolver->calculate($args, 2015);
		$this->assertSame(15, $result['day']);
		$this->assertSame(3, $result['month']);

		// After second change → second change
		$result = $resolver->calculate($args, 2026);
		$this->assertSame(22, $result['day']);
		$this->assertSame(6, $result['month']);
	}
}
