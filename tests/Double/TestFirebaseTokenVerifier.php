<?php

namespace App\Tests\Double;

use App\Service\FirebaseTokenVerifier;
use UnexpectedValueException;

class TestFirebaseTokenVerifier extends FirebaseTokenVerifier
{
	public function __construct()
	{
		parent::__construct('test-project-id');
	}

	public function verify(string $token): string
	{
		return match ($token) {
			'banned-token' => 'user-id-banned',
			'invalid-token' => throw new UnexpectedValueException('Invalid token'),
			default => 'user-id',
		};
	}
}
