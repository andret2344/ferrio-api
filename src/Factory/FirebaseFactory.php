<?php

namespace App\Factory;

use Kreait\Firebase\Factory;

class FirebaseFactory
{
	public static function create(string $credentials): Factory
	{
		$factory = new Factory();

		if ($credentials !== '') {
			$factory = $factory->withServiceAccount($credentials);
		}

		return $factory;
	}
}
