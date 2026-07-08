<?php

namespace App\Service;

use Kreait\Firebase\Contract\Auth;
use Psr\Log\LoggerInterface;
use Throwable;

class FirebaseUserLookup
{
	public function __construct(
		private readonly Auth            $auth,
		private readonly LoggerInterface $logger,
	)
	{
	}

	/**
	 * @param string[] $uids
	 *
	 * @return array<string, array{email: ?string, name: ?string, avatar: string}> keyed by UID
	 */
	public function lookup(array $uids): array
	{
		$uids = $uids
				|> array_filter(...)
				|> array_unique(...)
				|> array_values(...);
		if ($uids === []) {
			return [];
		}

		$result = [];
		foreach (array_chunk($uids, 100) as $chunk) {
			try {
				$records = $this->auth->getUsers($chunk);
				foreach ($records as $uid => $record) {
					if ($record !== null) {
						$result[$uid] = [
							'email' => $record->email,
							'name' => $record->displayName,
							'avatar' => $this->avatarUrl($uid, $record->photoUrl),
						];
					}
				}
			} catch (Throwable $e) {
				$this->logger->warning('Firebase getUsers failed: ' . $e->getMessage());
			}
		}

		foreach ($uids as $uid) {
			$result[$uid] ??= ['email' => null, 'name' => null, 'avatar' => $this->avatarUrl($uid, null)];
		}

		return $result;
	}

	/**
	 * Mirrors the Android client's avatar rule: the account's own photo when present, otherwise a
	 * deterministic Gravatar identicon keyed by a SHA-256 of the UID (stable per user, no PII in
	 * the URL).
	 */
	private function avatarUrl(string $uid, ?string $photoUrl): string
	{
		if ($photoUrl !== null && $photoUrl !== '') {
			return $photoUrl;
		}
		return sprintf('https://gravatar.com/avatar/%s?d=identicon', hash('sha256', $uid));
	}
}
