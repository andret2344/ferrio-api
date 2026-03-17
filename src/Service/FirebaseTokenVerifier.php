<?php

namespace App\Service;

use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Exception\AuthException;
use Psr\SimpleCache\CacheInterface;
use UnexpectedValueException;

class FirebaseTokenVerifier
{
	private const int UID_CACHE_TTL = 300; // 5 minutes

	public function __construct(
		private readonly Auth           $auth,
		private readonly CacheInterface $cache,
	)
	{
	}

	/**
	 * Verifies a Firebase ID token, checks the user exists and is not anonymous.
	 *
	 * @throws UnexpectedValueException if the token is invalid, user doesn't exist, or is anonymous
	 */
	public function verify(string $token): string
	{
		try {
			$verifiedToken = $this->auth->verifyIdToken($token);
			$uid = $verifiedToken->claims()
				->get('sub');

			if (empty($uid)) {
				throw new UnexpectedValueException('Missing subject');
			}

			$firebase = $verifiedToken->claims()
				->get('firebase');
			if (is_array($firebase) && ($firebase['sign_in_provider'] ?? null) === 'anonymous') {
				throw new UnexpectedValueException('Anonymous users are not allowed');
			}

			return $this->verifyUid($uid);
		} catch (FailedToVerifyToken $e) {
			throw new UnexpectedValueException('Invalid token: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Verifies that a raw Firebase UID exists via the Admin SDK (cached).
	 *
	 * @throws UnexpectedValueException if the UID does not exist in Firebase or belongs to an anonymous user
	 */
	public function verifyUid(string $uid): string
	{
		$cacheKey = 'firebase_uid_' . preg_replace('/[^a-zA-Z0-9_.]/', '_', $uid);

		if ($this->cache->has($cacheKey)) {
			return $this->cache->get($cacheKey);
		}

		try {
			$userRecord = $this->auth->getUser($uid);
			$verifiedUid = $userRecord->uid;

			if ($userRecord->providerData === [] && $userRecord->email === null) {
				throw new UnexpectedValueException('Anonymous users are not allowed');
			}

			$this->cache->set($cacheKey, $verifiedUid, self::UID_CACHE_TTL);

			return $verifiedUid;
		} catch (AuthException $e) {
			throw new UnexpectedValueException('Invalid UID: ' . $e->getMessage(), 0, $e);
		}
	}
}
