<?php

namespace App\Handler;

use App\Entity\Country;

trait CountryLookupTrait
{
	public function getCountry(?string $code): ?Country
	{
		$normalized = Country::normalizeCode($code);
		if ($normalized === null) {
			return null;
		}
		return $this->entityManager->getRepository(Country::class)
			->findOneBy(['isoCode' => $normalized]);
	}
}
