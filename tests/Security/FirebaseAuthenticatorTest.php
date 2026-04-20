<?php

namespace App\Tests\Security;

use App\Security\FirebaseAuthenticator;
use App\Security\FirebaseUser;
use App\Service\FirebaseTokenVerifier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use UnexpectedValueException;

class FirebaseAuthenticatorTest extends TestCase
{
	private FirebaseTokenVerifier&MockObject $tokenVerifier;
	private FirebaseAuthenticator $authenticator;

	protected function setUp(): void
	{
		$this->tokenVerifier = $this->createMock(FirebaseTokenVerifier::class);
		$this->authenticator = new FirebaseAuthenticator($this->tokenVerifier);
	}

	public function testSupportsReturnsTrueWhenAuthorizationHeaderPresent(): void
	{
		$request = Request::create('/test');
		$request->headers->set('Authorization', 'Bearer some-token');

		$this->assertTrue($this->authenticator->supports($request));
	}

	public function testSupportsReturnsFalseWhenNoAuthorizationHeader(): void
	{
		$request = Request::create('/test');

		$this->assertFalse($this->authenticator->supports($request));
	}

	public function testAuthenticateSucceedsWithValidBearerToken(): void
	{
		$request = Request::create('/test');
		$request->headers->set('Authorization', 'Bearer valid-token');

		$this->tokenVerifier->expects($this->once())
			->method('verify')
			->with('valid-token')
			->willReturn('firebase-uid-123');

		$passport = $this->authenticator->authenticate($request);
		$badge = $passport->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class);

		$this->assertSame('firebase-uid-123', $badge->getUserIdentifier());
	}

	public function testAuthenticateThrowsOnMissingBearerPrefix(): void
	{
		$request = Request::create('/test');
		$request->headers->set('Authorization', 'Basic some-token');

		$this->expectException(CustomUserMessageAuthenticationException::class);
		$this->expectExceptionMessage('Missing or invalid Authorization header');

		$this->authenticator->authenticate($request);
	}

	public function testAuthenticateFallsBackToVerifyUidOnTokenFailure(): void
	{
		$request = Request::create('/test');
		$request->headers->set('Authorization', 'Bearer raw-uid');

		$this->tokenVerifier->expects($this->once())
			->method('verify')
			->with('raw-uid')
			->willThrowException(new UnexpectedValueException('Invalid token'));

		$this->tokenVerifier->expects($this->once())
			->method('verifyUid')
			->with('raw-uid')
			->willReturn('raw-uid');

		$passport = $this->authenticator->authenticate($request);
		$badge = $passport->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class);

		$this->assertSame('raw-uid', $badge->getUserIdentifier());
	}

	public function testAuthenticateThrowsWhenBothVerifyAndVerifyUidFail(): void
	{
		$request = Request::create('/test');
		$request->headers->set('Authorization', 'Bearer bad-token');

		$this->tokenVerifier->expects($this->once())
			->method('verify')
			->willThrowException(new UnexpectedValueException('Invalid token'));

		$this->tokenVerifier->expects($this->once())
			->method('verifyUid')
			->willThrowException(new UnexpectedValueException('Invalid UID'));

		$this->expectException(CustomUserMessageAuthenticationException::class);
		$this->expectExceptionMessage('Invalid token');

		$this->authenticator->authenticate($request);
	}

	public function testOnAuthenticationSuccessReturnsNull(): void
	{
		$request = Request::create('/test');
		$token = new NullToken();

		$result = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

		$this->assertNull($result);
	}

	public function testOnAuthenticationFailureReturns401Json(): void
	{
		$request = Request::create('/test');
		$exception = new CustomUserMessageAuthenticationException('Invalid token');

		$response = $this->authenticator->onAuthenticationFailure($request, $exception);

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame(401, $response->getStatusCode());
		$this->assertJsonStringEqualsJsonString(
			'{"error":"Invalid token"}',
			$response->getContent()
		);
	}
}
