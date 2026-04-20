<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

readonly class LoggingService
{
	public function __construct(private LoggerInterface $logger)
	{
	}

	public function route(Request $request): void
	{
		$requestId = bin2hex(random_bytes(8));
		$request->attributes->set('request_id', $requestId);

		$this->logger->info('Incoming request', [
			'request_id' => $requestId,
			'method' => $request->getMethod(),
			'path' => $request->getPathInfo(),
			'query' => $request->query->all(),
			'ip' => $request->getClientIp(),
			'user_agent' => $request->headers->get('User-Agent'),
		]);
	}
}
