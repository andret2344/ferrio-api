<?php

namespace App\Security;

use App\Service\FirebaseTokenVerifier;
use Override;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use UnexpectedValueException;

class FirebaseAuthenticator extends AbstractAuthenticator
{
	public function __construct(private readonly FirebaseTokenVerifier $tokenVerifier)
	{
	}

	#[Override]
	public function supports(Request $request): ?bool
	{
		return $request->headers->has('Authorization');
	}

	#[Override]
	public function authenticate(Request $request): SelfValidatingPassport
	{
		$authHeader = $request->headers->get('Authorization', '');
		if (!str_starts_with($authHeader, 'Bearer ')) {
			throw new CustomUserMessageAuthenticationException('Missing or invalid Authorization header');
		}

		$token = substr($authHeader, 7);

		try {
			$uid = $this->tokenVerifier->verify($token);
		} catch (UnexpectedValueException) {
			try {
				$uid = $this->tokenVerifier->verifyUid($token);
			} catch (UnexpectedValueException) {
				throw new CustomUserMessageAuthenticationException('Invalid token');
			}
		}

		return new SelfValidatingPassport(
			new UserBadge($uid, fn(string $uid) => new FirebaseUser($uid))
		);
	}

	#[Override]
	public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
	{
		return null;
	}

	#[Override]
	public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
	{
		return new JsonResponse(
			['error' => $exception->getMessageKey()],
			Response::HTTP_UNAUTHORIZED
		);
	}
}
