<?php

namespace App\Tests\Service;

use App\Enum\ApiHitGrouping;
use App\Service\ApiHitStats;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
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

	/**
	 * Counting starts on Monday 2026-05-18 at 07:00 and "now" is 2026-07-14 13:20, so every grouping
	 * has a partial period at the start (except hours) and one still in progress at the end.
	 */
	private const array TRIMMED_ROWS = [
		['bucket_hour' => '2026-05-18 07:00:00', 'path' => '/v3/holidays', 'hits' => 1],
		['bucket_hour' => '2026-05-19 10:00:00', 'path' => '/v3/holidays', 'hits' => 2],
		['bucket_hour' => '2026-05-25 10:00:00', 'path' => '/v3/holidays', 'hits' => 4],
		['bucket_hour' => '2026-06-03 10:00:00', 'path' => '/v3/holidays', 'hits' => 8],
		['bucket_hour' => '2026-07-01 00:00:00', 'path' => '/v2/report', 'hits' => 16],
		['bucket_hour' => '2026-07-14 13:00:00', 'path' => '/v2/report', 'hits' => 32],
	];

	public function testNormalizePathDropsNumericSegments(): void
	{
		self::assertSame('/v2/holiday/en/day', ApiHitStats::normalizePath('/v2/holiday/en/day/7/7'));
		self::assertSame('/v3/holidays', ApiHitStats::normalizePath('/v3/holidays'));
	}

	public function testAggregateGroupsByDay(): void
	{
		$result = self::aggregateAll(ApiHitGrouping::DAY);

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

	public function testAggregateSplitsEndpointsPerVersionBusiestFirst(): void
	{
		$result = self::aggregateAll(ApiHitGrouping::DAY);

		self::assertSame(['v2', 'v3'], array_keys($result['paths_by_version']));
		self::assertSame([
			'/v2/report' => 9,
			'/v2/holiday/en/day' => 5,
		], $result['paths_by_version']['v2']);
		self::assertSame([
			'/v3/users/reports' => 9,
			'/v3/holidays' => 5,
		], $result['paths_by_version']['v3']);
	}

	public function testAggregateGroupsByHour(): void
	{
		$result = self::aggregateAll(ApiHitGrouping::HOUR);

		self::assertSame(['2026-07-02 10:00', '2026-07-01 11:00', '2026-07-01 10:00'], $result['periods']);
		self::assertSame(3, $result['period_totals']['2026-07-01 10:00']);
	}

	public function testAggregateGroupsByMonth(): void
	{
		$result = self::aggregateAll(ApiHitGrouping::MONTH);

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

		$result = self::aggregateAll(ApiHitGrouping::WEEK, $rows);

		self::assertSame(['2026-W01', '2025-W52'], $result['periods']);
	}

	/**
	 * @return iterable<string, array{ApiHitGrouping, list<string>}>
	 */
	public static function trimmedPeriods(): iterable
	{
		yield 'month' => [ApiHitGrouping::MONTH, ['2026-06']];
		yield 'week' => [ApiHitGrouping::WEEK, ['2026-W27', '2026-W23', '2026-W22']];
		yield 'day' => [ApiHitGrouping::DAY, ['2026-07-01', '2026-06-03', '2026-05-25', '2026-05-19']];
		yield 'hour' => [ApiHitGrouping::HOUR, [
			'2026-07-01 00:00',
			'2026-06-03 10:00',
			'2026-05-25 10:00',
			'2026-05-19 10:00',
			'2026-05-18 07:00',
		]];
	}

	/**
	 * @param list<string> $expected
	 */
	#[DataProvider('trimmedPeriods')]
	public function testAggregateDropsPartialPeriodsAtBothEnds(ApiHitGrouping $grouping, array $expected): void
	{
		$result = ApiHitStats::aggregate(
			self::TRIMMED_ROWS,
			$grouping,
			self::utc('2026-05-18 07:00'),
			self::utc('2026-07-14 13:20'),
		);

		self::assertSame($expected, $result['periods']);
		self::assertSame($expected, array_keys($result['period_totals']));
		self::assertEqualsCanonicalizing($expected, array_keys($result['version_cells']));
		self::assertEqualsCanonicalizing($expected, array_keys($result['cells']));
	}

	/**
	 * The doughnuts have no time axis, so a trimmed period still counts towards them.
	 */
	public function testAggregateKeepsTrimmedPeriodsInTheTotals(): void
	{
		$result = ApiHitStats::aggregate(
			self::TRIMMED_ROWS,
			ApiHitGrouping::MONTH,
			self::utc('2026-05-18 07:00'),
			self::utc('2026-07-14 13:20'),
		);

		self::assertSame(63, $result['total']);
		self::assertSame(['v2' => 48, 'v3' => 15], $result['version_totals']);
	}

	/**
	 * @param list<array{bucket_hour: string, path: string, hits: int}> $rows
	 */
	private static function aggregateAll(ApiHitGrouping $grouping, array $rows = self::ROWS): array
	{
		// Wide enough that no period of the rows above is partial.
		return ApiHitStats::aggregate($rows, $grouping, self::utc('2025-12-01 00:00'), self::utc('2026-08-01 00:00'));
	}

	private static function utc(string $at): DateTimeImmutable
	{
		return new DateTimeImmutable($at, new DateTimeZone('UTC'));
	}
}
