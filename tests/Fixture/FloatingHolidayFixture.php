<?php

namespace App\Tests\Fixture;

use App\Entity\FloatingHoliday;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\Language;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

class FloatingHolidayFixture extends Fixture implements DependentFixtureInterface
{
	#[Override]
	public function load(ObjectManager $manager): void
	{
		$languageEn = $this->getReference(LanguageFixture::LANGUAGE_EN, Language::class);
		$metadata = $this->getReference('floating-holiday-metadata', FloatingHolidayMetadata::class);

		$holiday = new FloatingHoliday($languageEn, $metadata, 'Floating Test Day', 'A floating holiday', null);
		$manager->persist($holiday);
		$this->addReference('floating-holiday-en', $holiday);

		$manager->flush();
	}

	#[Override]
	public function getDependencies(): array
	{
		return [
			LanguageFixture::class,
			FloatingHolidayMetadataFixture::class,
		];
	}
}
