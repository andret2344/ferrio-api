<?php

declare(strict_types=1);

namespace App\Tests\Fixture;

use App\Entity\Country;
use App\Entity\FixedHolidaySuggestion;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

class FixedHolidaySuggestionFixture extends Fixture implements DependentFixtureInterface
{
	#[Override]
	public function load(ObjectManager $manager): void
	{
		$country = $this->getReference('country-gb', Country::class);
		$suggestion = new FixedHolidaySuggestion('user-id', 'Test name', 'Test description', 1, 1, $country, new DateTimeImmutable("2024-06-06 20:30:40"));
		$manager->persist($suggestion);
		$this->addReference('fixed-holiday-suggestion', $suggestion);
		$manager->flush();
	}

	#[Override]
	public function getDependencies(): array
	{
		return [
			CountryFixture::class
		];
	}
}
