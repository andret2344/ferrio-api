<?php

namespace App\Tests\Fixture;

use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use App\Entity\FloatingHoliday;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\Language;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

/**
 * Polish translations of the shared holiday metadata. Separate from FixedHolidayFixture, which only
 * carries English rows: the import diff reads the Polish source language exclusively.
 */
class PolishHolidayFixture extends Fixture implements DependentFixtureInterface
{
	public const string FIXED_0301_NAME = 'Dzień Pierwszy Marca';
	public const string FIXED_0314_NAME = 'Dzień Liczby Pi';
	public const string FLOATING_NAME = 'Święto Ruchome';

	#[Override]
	public function load(ObjectManager $manager): void
	{
		$languagePl = $this->getReference(LanguageFixture::LANGUAGE_PL, Language::class);
		$metadata0301 = $this->getReference(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);
		$metadata0314 = $this->getReference(FixedHolidayMetadataFixture::METADATA_0314, FixedHolidayMetadata::class);
		$floatingMetadata = $this->getReference('floating-holiday-metadata', FloatingHolidayMetadata::class);

		$manager->persist(new FixedHoliday($languagePl, $metadata0301, self::FIXED_0301_NAME, null, null));
		$manager->persist(new FixedHoliday($languagePl, $metadata0314, self::FIXED_0314_NAME, null, null));
		$manager->persist(new FloatingHoliday($languagePl, $floatingMetadata, self::FLOATING_NAME, null, null));

		$manager->flush();
	}

	#[Override]
	public function getDependencies(): array
	{
		return [
			LanguageFixture::class,
			FixedHolidayMetadataFixture::class,
			FloatingHolidayMetadataFixture::class,
		];
	}
}
