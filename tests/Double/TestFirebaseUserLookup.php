<?php

namespace App\Tests\Double;

use App\Service\FirebaseUserLookup;
use Override;

class TestFirebaseUserLookup extends FirebaseUserLookup
{
	public function __construct()
	{
	}

	#[Override]
	public function lookup(array $uids): array
	{
		$result = [];
		foreach (array_unique(array_filter($uids)) as $uid) {
			$result[$uid] = [
				'email' => null,
				'name' => null,
				'avatar' => sprintf('https://gravatar.com/avatar/%s?d=identicon', hash('sha256', $uid)),
			];
		}
		return $result;
	}
}
