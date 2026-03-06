<?php

namespace App\Service;

use Override;

class DevFirebaseTokenVerifier extends FirebaseTokenVerifier
{
	public function __construct()
	{
	}

	#[Override]
	public function verify(string $token): string
	{
		return $token;
	}

	#[Override]
	public function verifyUid(string $uid): string
	{
		return $uid;
	}
}
