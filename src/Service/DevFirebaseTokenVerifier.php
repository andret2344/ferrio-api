<?php

namespace App\Service;

use Override;

class DevFirebaseTokenVerifier extends FirebaseTokenVerifier
{
	public function __construct()
	{
		// empty
	}

	#[Override]
	public function verify(string $token): string
	{
		$parts = explode('.', $token);
		if (count($parts) === 3) {
			$payload = json_decode(base64_decode($parts[1]), true);
			if (isset($payload['sub'])) {
				return $payload['sub'];
			}
		}

		return $token;
	}

	#[Override]
	public function verifyUid(string $uid): string
	{
		return $uid;
	}
}
