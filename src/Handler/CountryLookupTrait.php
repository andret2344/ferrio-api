<?php

namespace App\Handler;

use App\Entity\Country;

trait CountryLookupTrait
{
	public function getCountry(?string $country): ?Country
	{
		if ($country === null || $country === 'null' || $country === '') {
			return null;
		}
		return $this->entityManager->getRepository(Country::class)
			->findOneBy(['isoCode' => $country]);
	}
}
