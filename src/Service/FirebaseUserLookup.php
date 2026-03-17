<?php

namespace App\Service;

use Kreait\Firebase\Contract\Auth;
use Psr\Log\LoggerInterface;

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
	 * @return array<string, array{email: ?string, name: ?string}> keyed by UID
	 */
	public function lookup(array $uids): array
	{
		$uids = array_values(array_unique(array_filter($uids)));
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
						];
					}
				}
			} catch (\Throwable $e) {
				$this->logger->warning('Firebase getUsers failed: ' . $e->getMessage());
			}
		}

		foreach ($uids as $uid) {
			$result[$uid] ??= ['email' => null, 'name' => null];
		}

		return $result;
	}
}
