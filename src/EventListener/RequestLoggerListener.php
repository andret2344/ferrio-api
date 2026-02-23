<?php

namespace App\EventListener;

use App\Service\LoggingService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest')]
readonly class RequestLoggerListener
{
	public function __construct(private LoggingService $loggingService)
	{
	}

	public function onKernelRequest(RequestEvent $event): void
	{
		if (!$event->isMainRequest()) {
			return;
		}

		$this->loggingService->route($event->getRequest());
	}
}
