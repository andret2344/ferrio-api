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
	 *     version_totals: array<string, int>,
	 *     total: int
	 * }
	 */
	public function collect(ApiHitGrouping $grouping, int $days): array
	{
		$sql = 'SELECT bucket_hour, path, hits FROM api_hit';
		$params = [];
		if ($days > 0) {
			$sql .= ' WHERE bucket_hour >= ?';
			$params[] = new DateTimeImmutable(sprintf('-%d days', $days), new DateTimeZone('UTC'))
				->format('Y-m-d H:00:00');
		}
		return self::aggregate($this->connection->fetchAllAssociative($sql, $params), $grouping);
	}

	/**
	 * @param list<array{bucket_hour: string, path: string, hits: int|string}> $rows
	 */
	public static function aggregate(array $rows, ApiHitGrouping $grouping): array
	{
		$cells = [];
		$versionCells = [];
		$periodTotals = [];
		$pathTotals = [];
		$versionTotals = [];
		$total = 0;

		foreach ($rows as $row) {
			$path = self::normalizePath((string)$row['path']);
			$version = self::version($path);
			$period = (new DateTimeImmutable((string)$row['bucket_hour']))->format($grouping->format());
			$hits = (int)$row['hits'];

			$cells[$period][$path] = ($cells[$period][$path] ?? 0) + $hits;
			$versionCells[$period][$version] = ($versionCells[$period][$version] ?? 0) + $hits;
			$periodTotals[$period] = ($periodTotals[$period] ?? 0) + $hits;
			$pathTotals[$path] = ($pathTotals[$path] ?? 0) + $hits;
			$versionTotals[$version] = ($versionTotals[$version] ?? 0) + $hits;
			$total += $hits;
		}

		krsort($periodTotals);
		arsort($pathTotals);
		ksort($versionTotals);

		return [
			'periods' => array_keys($periodTotals),
			'paths' => array_keys($pathTotals),
			'versions' => array_keys($versionTotals),
			'cells' => $cells,
			'version_cells' => $versionCells,
			'period_totals' => $periodTotals,
			'path_totals' => $pathTotals,
			'version_totals' => $versionTotals,
			'total' => $total,
		];
	}

	public static function normalizePath(string $path): string
	{
		$segments = array_filter(explode('/', $path), fn(string $segment) => !ctype_digit($segment));
		return implode('/', $segments);
	}

	private static function version(string $path): string
	{
		// api_hit only ever stores versioned paths (ApiHitCounter guards on /v\d+), so a match is
		// guaranteed; the fallback exists only to keep the return type total.
		preg_match('#^/(v\d+)(/|$)#', $path, $matches);
		return $matches[1] ?? 'unknown';
	}
}
