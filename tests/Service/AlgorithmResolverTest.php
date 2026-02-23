<?php

namespace App\Tests\Service;

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
}
