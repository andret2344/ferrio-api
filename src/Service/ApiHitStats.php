<?php

namespace App\Service;

use App\Enum\ApiHitGrouping;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;

/**
 * Reads the `api_hit` table and folds it into a period × endpoint matrix for the admin stats page.
 *
 * Endpoints are normalised by dropping every numeric path segment, so `/v2/holiday/en/day/7/7` and
 * `/v2/holiday/en/day/8/7` both count towards `/v2/holiday/en/day`.
 */
readonly class ApiHitStats
{
	public function __construct(private Connection $connection)
	{
	}

	/**
	 * @param int $days number of days back to include, 0 for everything
	 *
	 * @return array{
	 *     periods: list<string>,
	 *     paths: list<string>,
	 *     versions: list<string>,
	 *     cells: array<string, array<string, int>>,
	 *     version_cells: array<string, array<string, int>>,
	 *     period_totals: array<string, int>,
	 *     path_totals: array<string, int>,
	 *     paths_by_version: array<string, array<string, int>>,
	 *     version_totals: array<string, int>,
	 *     total: int
	 * }
	 */
	public function collect(ApiHitGrouping $grouping, int $days): array
	{
		$now = self::now();
		$sql = 'SELECT bucket_hour, path, hits FROM api_hit';
		$params = [];
		if ($days > 0) {
			$sql .= ' WHERE bucket_hour >= ?';
			$params[] = self::windowStart($days, $now)->format('Y-m-d H:i:s');
		}
		$rows = $this->connection->fetchAllAssociative($sql, $params);
		return self::aggregate($rows, $grouping, $this->dataStart($days, $now) ?? $now, $now);
	}

	/**
	 * The groupings that fit at least one whole period into the window, i.e. the ones whose traffic
	 * chart would not come out empty after aggregate() trims the partial periods. Empty when nothing
	 * has been counted yet.
	 *
	 * @return list<ApiHitGrouping>
	 */
	public function groupingsWithCompletePeriods(int $days): array
	{
		$now = self::now();
		$from = $this->dataStart($days, $now);
		if ($from === null) {
			return [];
		}
		return array_values(array_filter(
			ApiHitGrouping::cases(),
			fn(ApiHitGrouping $grouping) => $grouping->hasCompletePeriod($from, $now),
		));
	}

	/**
	 * Only periods lying wholly between `$from` and `$now` reach the period-keyed outputs (`periods`,
	 * `cells`, `version_cells`, `period_totals`): a period the data starts in the middle of, or the one
	 * still in progress, would plot as a false dip at either end of the traffic chart. The remaining
	 * outputs have no time axis and keep counting every row.
	 *
	 * @param list<array{bucket_hour: string, path: string, hits: int|string}> $rows
	 * @param DateTimeImmutable $from when the data starts - the window's lower edge or the first hit ever
	 */
	public static function aggregate(
		array             $rows,
		ApiHitGrouping    $grouping,
		DateTimeImmutable $from,
		DateTimeImmutable $now,
	): array
	{
		$utc = new DateTimeZone('UTC');
		$firstStart = $grouping->firstCompletePeriodStart($from);
		$currentStart = $grouping->periodStart($now);

		$cells = [];
		$versionCells = [];
		$periodTotals = [];
		$pathTotals = [];
		$pathsByVersion = [];
		$versionTotals = [];
		$total = 0;

		foreach ($rows as $row) {
			$path = self::normalizePath((string)$row['path']);
			$version = self::version($path);
			$at = new DateTimeImmutable((string)$row['bucket_hour'], $utc);
			$hits = (int)$row['hits'];

			// $firstStart and $currentStart are period boundaries, so comparing the row's hour against
			// them decides for its whole period.
			if ($at >= $firstStart && $at < $currentStart) {
				$period = $at->format($grouping->format());
				$cells[$period][$path] = ($cells[$period][$path] ?? 0) + $hits;
				$versionCells[$period][$version] = ($versionCells[$period][$version] ?? 0) + $hits;
				$periodTotals[$period] = ($periodTotals[$period] ?? 0) + $hits;
			}
			$pathTotals[$path] = ($pathTotals[$path] ?? 0) + $hits;
			$pathsByVersion[$version][$path] = ($pathsByVersion[$version][$path] ?? 0) + $hits;
			$versionTotals[$version] = ($versionTotals[$version] ?? 0) + $hits;
			$total += $hits;
		}

		krsort($periodTotals);
		arsort($pathTotals);
		ksort($versionTotals);
		// Each version's endpoints ordered by traffic, so a caller that wants the busiest N (the
		// per-version doughnuts on the stats page) can just slice off the front.
		ksort($pathsByVersion);
		foreach ($pathsByVersion as &$paths) {
			arsort($paths);
		}
		unset($paths);

		return [
			'periods' => array_keys($periodTotals),
			'paths' => array_keys($pathTotals),
			'versions' => array_keys($versionTotals),
			'cells' => $cells,
			'version_cells' => $versionCells,
			'period_totals' => $periodTotals,
			'path_totals' => $pathTotals,
			'paths_by_version' => $pathsByVersion,
			'version_totals' => $versionTotals,
			'total' => $total,
		];
	}

	private static function now(): DateTimeImmutable
	{
		return new DateTimeImmutable('now', new DateTimeZone('UTC'));
	}

	private static function windowStart(int $days, DateTimeImmutable $now): DateTimeImmutable
	{
		return ApiHitGrouping::HOUR->periodStart($now->modify(sprintf('-%d days', $days)));
	}

	/**
	 * Where the counted data begins: the window's lower edge, or the first hit ever recorded when
	 * counting started later than that. Null when nothing has been counted yet.
	 */
	private function dataStart(int $days, DateTimeImmutable $now): ?DateTimeImmutable
	{
		$first = $this->connection->fetchOne('SELECT MIN(bucket_hour) FROM api_hit');
		if ($first === null || $first === false) {
			return null;
		}
		$from = new DateTimeImmutable((string)$first, new DateTimeZone('UTC'));
		if ($days > 0) {
			$windowStart = self::windowStart($days, $now);
			if ($windowStart > $from) {
				return $windowStart;
			}
		}
		return $from;
	}

	public static function normalizePath(string $path): string
	{
		$segments = array_filter(explode('/', $path), fn(string $segment) => !ctype_digit($segment));
		return implode('/', $segments);
	}

	private static function version(string $path): string
	{
		// api_hit only ever stores paths under a known version (ApiHitCounter::isCountable), so a
		// match is guaranteed; the fallback exists only to keep the return type total. Rows written
		// before that guard was tightened may still carry junk like `/v543.php` — those land in
		// 'unknown' rather than inventing a version column.
		preg_match('#^/(v\d+)(/|$)#', $path, $matches);
		return $matches[1] ?? 'unknown';
	}
}
