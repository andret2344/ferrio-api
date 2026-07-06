<?php

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class ApiHitCounter
{
	public function __construct(
		private Connection $connection,
		private LoggerInterface $logger
	) {
	}

	/**
	 * Records one hit for the versioned API the request path targets (/v1, /v2, /v3),
	 * bucketed by UTC hour. Non-versioned paths are ignored. Never throws — analytics
	 * must not break the request.
	 */
	public function count(string $path): void
	{
		if (preg_match('#^/(v\d+)\b#', $path, $matches) !== 1) {
			return;
		}
		$version = $matches[1];
		$bucketHour = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:00:00');
		try {
			$this->connection->executeStatement(
				'INSERT INTO api_hit (bucket_hour, version, hits) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1',
				[$bucketHour, $version]
			);
		} catch (Throwable $exception) {
			$this->logger->warning('Failed to record API hit', [
				'version' => $version,
				'exception' => $exception->getMessage(),
			]);
		}
	}
}
