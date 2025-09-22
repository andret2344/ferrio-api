<?php

declare(strict_types=1);

namespace App\Tests\Fixture;

use App\Entity\Country;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Override;

class CountryFixture extends Fixture
{
	#[Override]
	public function load(ObjectManager $manager): void
	{
		$countryGb = new Country('GB', 'Great Britain');
		$manager->persist($countryGb);
		$this->addReference('country-gb', $countryGb);

		$countryPl = new Country('PL', 'Poland');
		$manager->persist($countryPl);
		$this->addReference('country-pl', $countryPl);

		$manager->flush();
	}
}
