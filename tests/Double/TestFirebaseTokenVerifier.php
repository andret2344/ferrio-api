<?php

namespace App\Tests\Double;

use App\Service\FirebaseTokenVerifier;
use Override;
use UnexpectedValueException;

class TestFirebaseTokenVerifier extends FirebaseTokenVerifier
{
	public function __construct()
	{
	}

	#[Override]
	public function verify(string $token): string
	{
		return match ($token) {
			'banned-token' => 'user-id-banned',
			'anonymous-token' => throw new UnexpectedValueException('Anonymous users are not allowed'),
			'invalid-token' => throw new UnexpectedValueException('Invalid token'),
			default => 'user-id',
		};
	}

	#[Override]
	public function verifyUid(string $uid): string
	{
		return match ($uid) {
			'anonymous-token' => throw new UnexpectedValueException('Anonymous users are not allowed'),
			'invalid-token' => throw new UnexpectedValueException('Invalid UID'),
			default => $uid,
		};
	}
}
