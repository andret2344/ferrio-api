<?php

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class ApiHitCounter
{
	/**
	 * Every API version whose traffic is counted. Widen this when a new version ships —
	 * a version missing here is silently dropped from /admin/stats.
	 */
	private const array VERSIONS = ['v2', 'v3'];

	public function __construct(
		private Connection      $connection,
		private LoggerInterface $logger
	)
	{
	}

	/**
	 * Records one hit for a versioned API request, keyed by the full request path and bucketed
	 * by UTC hour. Only GET requests are counted — analytics tracks read traffic per endpoint,
	 * not writes. Never throws — analytics must not break the request.
	 */
	public function count(string $method, string $path): void
	{
		if (!self::isCountable($method, $path)) {
			return;
		}
		// ponytail: INSERT ... ON DUPLICATE KEY UPDATE is MySQL-only; add a platform branch if
		// analytics ever needs to run on another DB (the test DB is SQLite, where this no-ops).
		if (!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
			return;
		}
		$bucketHour = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:00:00');
		try {
			$this->connection->executeStatement(
				'INSERT INTO api_hit (bucket_hour, path, hits) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1',
				[$bucketHour, $path]
			);
		} catch (Throwable $exception) {
			$this->logger->warning('Failed to record API hit', [
				'path' => $path,
				'exception' => $exception->getMessage(),
			]);
		}
	}

	/**
	 * A hit is counted when it is a GET on a path served by a *known* API version (see VERSIONS).
	 * The path does not have to resolve to a route — a 404 under /v3 is real client traffic worth
	 * seeing on the stats page (a mobile build hitting a typo'd URL, say). What is excluded is
	 * traffic that was never aimed at the API at all:
	 *
	 * - unknown versions (`/v543.php`, `/v999/anything`) — vulnerability scanners walk a fixed list
	 *   of paths, and counting them lets a bot mint an unbounded number of (bucket_hour, path) rows;
	 * - `.php` suffixes (`/v2/config.php`) — this is a Symfony app with a single front controller,
	 *   so nothing under a version prefix legitimately ends in .php; it is always a scanner.
	 *
	 * Admin UI and asset paths carry no version prefix and so never match in the first place.
	 */
	public static function isCountable(string $method, string $path): bool
	{
		if ($method !== 'GET') {
			return false;
		}
		if (str_ends_with($path, '.php')) {
			return false;
		}
		$versions = implode('|', self::VERSIONS);
		return preg_match(sprintf('#^/(%s)(/|$)#', $versions), $path) === 1;
	}
}
