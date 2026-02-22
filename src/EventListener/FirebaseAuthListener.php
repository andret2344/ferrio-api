<?php

namespace App\EventListener;

use App\Attribute\FirebaseAuth;
use App\Service\FirebaseTokenVerifier;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use UnexpectedValueException;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest', priority: 10)]
readonly class FirebaseAuthListener
{
	public function __construct(private FirebaseTokenVerifier $tokenVerifier)
	{
	}

	public function onKernelRequest(RequestEvent $event): void
	{
		if (!$event->isMainRequest()) {
			return;
		}

		$request = $event->getRequest();
		$controller = $request->attributes->get('_controller');

		if (!$controller || !$this->requiresFirebaseAuth($controller)) {
			return;
		}

		$authHeader = $request->headers->get('Authorization', '');
		if (!str_starts_with($authHeader, 'Bearer ')) {
			$event->setResponse(new JsonResponse(
				['error' => 'Missing or invalid Authorization header'],
				Response::HTTP_UNAUTHORIZED
			));
			return;
		}

		$token = substr($authHeader, 7);

		try {
			$uid = $this->tokenVerifier->verify($token);
		} catch (UnexpectedValueException) {
			$event->setResponse(new JsonResponse(
				['error' => 'Invalid token'],
				Response::HTTP_UNAUTHORIZED
			));
			return;
		}

		$request->attributes->set('firebaseUid', $uid);
	}

	private function requiresFirebaseAuth(string $controller): bool
	{
		if (!str_contains($controller, '::')) {
			return false;
		}

		[$class, $method] = explode('::', $controller, 2);

		if (!class_exists($class)) {
			return false;
		}

		$refMethod = new ReflectionMethod($class, $method);
		if ($refMethod->getAttributes(FirebaseAuth::class, ReflectionAttribute::IS_INSTANCEOF) !== []) {
			return true;
		}

		$refClass = new ReflectionClass($class);
		return $refClass->getAttributes(FirebaseAuth::class, ReflectionAttribute::IS_INSTANCEOF) !== [];
	}
}
