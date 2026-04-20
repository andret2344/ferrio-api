<?php

namespace App\Factory;

use Kreait\Firebase\Factory;

class FirebaseFactory
{
	public static function create(string $credentials): Factory
	{
		$factory = new Factory();

		if ($credentials !== '') {
			$decoded = base64_decode($credentials, true);
			if ($decoded !== false) {
				$serviceAccount = json_decode($decoded, true);
				if (is_array($serviceAccount)) {
					return $factory->withServiceAccount($serviceAccount);
				}
			}

			$factory = $factory->withServiceAccount($credentials);
		}

		return $factory;
	}
}
