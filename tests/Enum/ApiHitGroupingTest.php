<?php

namespace App\Tests\Enum;

use App\Enum\ApiHitGrouping;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class ApiHitGroupingTest extends TestCase
{
	public function testPeriodStartUsesIsoWeeksAndCalendarMonths(): void
	{
		// 2026-05-24 is a Sunday, the last day of the ISO week that starts on Monday 2026-05-18.
		$at = self::utc('2026-05-24 13:00');

		self::assertEquals(self::utc('2026-05-24 13:00'), ApiHitGrouping::HOUR->periodStart($at));
		self::assertEquals(self::utc('2026-05-24 00:00'), ApiHitGrouping::DAY->periodStart($at));
		self::assertEquals(self::utc('2026-05-18 00:00'), ApiHitGrouping::WEEK->periodStart($at));
		self::assertEquals(self::utc('2026-05-01 00:00'), ApiHitGrouping::MONTH->periodStart($at));
	}

	public function testFirstCompletePeriodStartKeepsAnExactBoundary(): void
	{
		self::assertEquals(
			self::utc('2026-06-01 00:00'),
			ApiHitGrouping::MONTH->firstCompletePeriodStart(self::utc('2026-06-01 00:00')),
		);
		self::assertEquals(
			self::utc('2026-06-01 00:00'),
			ApiHitGrouping::MONTH->firstCompletePeriodStart(self::utc('2026-05-18 07:00')),
		);
	}

	public function testOneDayWindowHoldsOnlyWholeHours(): void
	{
		$from = self::utc('2026-07-13 13:00');
		$now = self::utc('2026-07-14 13:20');

		self::assertTrue(ApiHitGrouping::HOUR->hasCompletePeriod($from, $now));
		self::assertFalse(ApiHitGrouping::DAY->hasCompletePeriod($from, $now));
		self::assertFalse(ApiHitGrouping::WEEK->hasCompletePeriod($from, $now));
		self::assertFalse(ApiHitGrouping::MONTH->hasCompletePeriod($from, $now));
	}

	public function testPeriodEndingExactlyNowIsComplete(): void
	{
		self::assertTrue(ApiHitGrouping::MONTH->hasCompletePeriod(self::utc('2026-06-01 00:00'), self::utc('2026-07-01 00:00')));
	}

	public function testOrFinerWithinStepsDownToTheCoarsestAvailable(): void
	{
		$available = [ApiHitGrouping::HOUR, ApiHitGrouping::DAY];

		self::assertSame(ApiHitGrouping::DAY, ApiHitGrouping::MONTH->orFinerWithin($available));
		self::assertSame(ApiHitGrouping::DAY, ApiHitGrouping::DAY->orFinerWithin($available));
		self::assertSame(ApiHitGrouping::HOUR, ApiHitGrouping::HOUR->orFinerWithin($available));
		self::assertSame(ApiHitGrouping::WEEK, ApiHitGrouping::WEEK->orFinerWithin([]));
	}

	private static function utc(string $at): DateTimeImmutable
	{
		return new DateTimeImmutable($at, new DateTimeZone('UTC'));
	}
}
