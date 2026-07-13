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
	public function __construct(
		private Connection      $connection,
		private LoggerInterface $logger
	)
	{
	}

	/**
	 * Records one hit for a versioned API request (/v2, /v3, …), keyed by the full request
	 * path and bucketed by UTC hour. Only GET requests are counted — analytics tracks read
	 * traffic per endpoint, not writes. Non-versioned paths (admin UI, assets) are ignored.
	 * Never throws — analytics must not break the request.
	 */
	public function count(string $method, string $path): void
	{
		if ($method !== 'GET') {
			return;
		}
		if (preg_match('#^/v\d+\b#', $path) !== 1) {
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
}
