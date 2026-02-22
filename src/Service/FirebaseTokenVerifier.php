<?php

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use UnexpectedValueException;

class FirebaseTokenVerifier
{
	private const string GOOGLE_KEYS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

	public function __construct(private readonly string $firebaseProjectId)
	{
	}

	/**
	 * Verifies a Firebase ID token and returns the UID.
	 *
	 * @throws UnexpectedValueException if the token is invalid
	 */
	public function verify(string $token): string
	{
		$keys = $this->fetchPublicKeys();

		$jwkKeys = array_map(fn($cert) => new Key($cert, 'RS256'), $keys);

		$decoded = JWT::decode($token, $jwkKeys);

		$issuer = "https://securetoken.google.com/{$this->firebaseProjectId}";
		if ($decoded->iss !== $issuer) {
			throw new UnexpectedValueException('Invalid issuer');
		}

		if ($decoded->aud !== $this->firebaseProjectId) {
			throw new UnexpectedValueException('Invalid audience');
		}

		if (empty($decoded->sub)) {
			throw new UnexpectedValueException('Missing subject');
		}

		return $decoded->sub;
	}

	/**
	 * @return array<string, string>
	 */
	protected function fetchPublicKeys(): array
	{
		$response = file_get_contents(self::GOOGLE_KEYS_URL);
		if ($response === false) {
			throw new UnexpectedValueException('Failed to fetch Google public keys');
		}

		$keys = json_decode($response, true);
		if (!is_array($keys)) {
			throw new UnexpectedValueException('Invalid public keys response');
		}

		return $keys;
	}
}
