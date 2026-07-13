<?php

namespace App\Tests\Service;

use App\Enum\ApiHitGrouping;
use App\Service\ApiHitStats;
use PHPUnit\Framework\TestCase;

class ApiHitStatsTest extends TestCase
{
	private const array ROWS = [
		['bucket_hour' => '2026-07-01 10:00:00', 'path' => '/v2/holiday/en/day/7/7', 'hits' => 3],
		['bucket_hour' => '2026-07-01 11:00:00', 'path' => '/v2/holiday/en/day/8/7', 'hits' => 2],
		['bucket_hour' => '2026-07-02 10:00:00', 'path' => '/v3/holidays', 'hits' => 5],
		['bucket_hour' => '2026-07-02 10:00:00', 'path' => '/v3/users/reports', 'hits' => 9],
		['bucket_hour' => '2026-07-02 10:00:00', 'path' => '/v2/report', 'hits' => 9],
	];

	public function testNormalizePathDropsNumericSegments(): void
	{
		self::assertSame('/v2/holiday/en/day', ApiHitStats::normalizePath('/v2/holiday/en/day/7/7'));
		self::assertSame('/v3/holidays', ApiHitStats::normalizePath('/v3/holidays'));
	}

	public function testAggregateGroupsByDay(): void
	{
		$result = ApiHitStats::aggregate(self::ROWS, ApiHitGrouping::DAY);

		self::assertSame(['2026-07-02', '2026-07-01'], $result['periods']);
		self::assertSame([
			'/v3/users/reports' => 9,
			'/v2/report' => 9,
			'/v2/holiday/en/day' => 5,
			'/v3/holidays' => 5,
		], $result['path_totals']);
		self::assertSame(['v2' => 14, 'v3' => 14], $result['version_totals']);
		self::assertSame(5, $result['cells']['2026-07-01']['/v2/holiday/en/day']);
		self::assertSame(28, $result['total']);
	}

	public function testAggregateGroupsByHour(): void
	{
		$result = ApiHitStats::aggregate(self::ROWS, ApiHitGrouping::HOUR);

		self::assertSame(['2026-07-02 10:00', '2026-07-01 11:00', '2026-07-01 10:00'], $result['periods']);
		self::assertSame(3, $result['period_totals']['2026-07-01 10:00']);
	}

	public function testAggregateGroupsByMonth(): void
	{
		$result = ApiHitStats::aggregate(self::ROWS, ApiHitGrouping::MONTH);

		self::assertSame(['2026-07'], $result['periods']);
		self::assertSame(28, $result['period_totals']['2026-07']);
	}

	/**
	 * ISO week keys must sort chronologically as plain strings even across a year boundary:
	 * 2025-12-29 falls in ISO week 2026-W01, which must sort after 2025-W52.
	 */
	public function testAggregateGroupsByWeekAcrossYearBoundary(): void
	{
		$rows = [
			['bucket_hour' => '2025-12-22 10:00:00', 'path' => '/v3/holidays', 'hits' => 1],
			['bucket_hour' => '2025-12-29 10:00:00', 'path' => '/v3/holidays', 'hits' => 1],
		];

		$result = ApiHitStats::aggregate($rows, ApiHitGrouping::WEEK);

		self::assertSame(['2026-W01', '2025-W52'], $result['periods']);
	}
}
