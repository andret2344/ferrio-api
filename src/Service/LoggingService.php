<?php

namespace App\Service;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

readonly class LoggingService {
	public function __construct(ParameterBagInterface $params,
								private Logger        $log = new Logger('UHC')) {
		$log->pushHandler(new StreamHandler('log/latest.log', Level::fromName($params->get('logging_level'))));
	}

	public function route(Request $request): void {
		$this->log->debug($request->getPathInfo());
	}
}
