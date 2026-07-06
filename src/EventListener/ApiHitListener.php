<?php

namespace App\EventListener;

use App\Service\ApiHitCounter;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::TERMINATE, method: 'onKernelTerminate')]
readonly class ApiHitListener
{
	public function __construct(private ApiHitCounter $apiHitCounter)
	{
	}

	public function onKernelTerminate(TerminateEvent $event): void
	{
		if (!$event->isMainRequest()) {
			return;
		}

		$this->apiHitCounter->count($event->getRequest()->getPathInfo());
	}
}
