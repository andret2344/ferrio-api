<?php

namespace App\Service;

use DateTimeImmutable;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

readonly class LoggingService
{
	private Logger $log;

	public function __construct(ParameterBagInterface $params)
	{
		$this->log = new Logger('Ferrio');
		$this->log->pushHandler(new StreamHandler('log/latest.log', Level::fromName($params->get('logging_level'))));
	}

	public function route(Request $request): void
	{
		$queryString = http_build_query($request->query->all());
		$line = sprintf(
			'[%s] %s %s%s',
			new DateTimeImmutable()->format('Y-m-d H:i:s'),
			$request->getMethod(),
			$request->getPathInfo(),
			$queryString ? '?' . $queryString : ''
		);

		$this->log->debug($line);
	}
}
