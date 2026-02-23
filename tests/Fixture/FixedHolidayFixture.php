<?php

namespace App\Tests\Fixture;

use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use App\Entity\Language;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

class FixedHolidayFixture extends Fixture implements DependentFixtureInterface
{
	#[Override]
	public function load(ObjectManager $manager): void
	{
		$languageEn = $this->getReference(LanguageFixture::LANGUAGE_EN, Language::class);
		$metadata0301 = $this->getReference(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);
		$metadata0314 = $this->getReference(FixedHolidayMetadataFixture::METADATA_0314, FixedHolidayMetadata::class);

		$holiday0301 = new FixedHoliday($languageEn, $metadata0301, 'March First', 'A test fixed holiday', null);
		$manager->persist($holiday0301);
		$this->addReference('fixed-holiday-0301-en', $holiday0301);

		$holiday0314 = new FixedHoliday($languageEn, $metadata0314, 'Pi Day', 'Pi approximation day', null);
		$manager->persist($holiday0314);
		$this->addReference('fixed-holiday-0314-en', $holiday0314);

		$manager->flush();
	}

	#[Override]
	public function getDependencies(): array
	{
		return [
			LanguageFixture::class,
			FixedHolidayMetadataFixture::class,
		];
	}
}
