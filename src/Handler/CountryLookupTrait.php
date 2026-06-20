<?php

namespace App\Handler;

use App\Entity\Country;

trait CountryLookupTrait
{
	public function getCountry(?string $code): ?Country
	{
		return $this->pickCountry($this->getCountriesByCodes($code), $code);
	}

	/**
	 * @return array<string, Country> map of uppercase ISO code → Country
	 */
	public function getCountriesByCodes(?string ...$codes): array
	{
		$keys = [];
		foreach ($codes as $code) {
			$n = self::normalize($code);
			if ($n !== null) {
				$keys[$n] = true;
			}
		}
		if ($keys === []) {
			return [];
		}
		$list = $this->entityManager->getRepository(Country::class)
			->findBy(['isoCode' => array_keys($keys)]);
		return array_column($list, null, 'isoCode');
	}

	/**
	 * @param array<string, Country> $map
	 */
	public function pickCountry(array $map, ?string $code): ?Country
	{
		return $map[self::normalize($code) ?? ''] ?? null;
	}

	private static function normalize(?string $code): ?string
	{
		if ($code === null || $code === '' || $code === 'null') {
			return null;
		}
		return strtoupper($code);
	}
}
