<?php

namespace App\Service;

use Override;

class DevFirebaseTokenVerifier extends FirebaseTokenVerifier
{
	public function __construct()
	{
		parent::__construct('dev');
	}

	#[Override]
	public function verify(string $token): string
	{
		return $token;
	}
}
