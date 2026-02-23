<?php

declare(strict_types=1);

namespace App\Tests\Fixture;

use App\Entity\FloatingHolidayError;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\Language;
use App\Entity\ReportType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

class FloatingHolidayErrorFixture extends Fixture implements DependentFixtureInterface
{
	#[Override]
	public function load(ObjectManager $manager): void
	{
		$language = $this->getReference('language-en', Language::class);
		$metadata = $this->getReference('floating-holiday-metadata', FloatingHolidayMetadata::class);
		$error = new FloatingHolidayError('user-id', $language, $metadata, ReportType::OTHER, 'Test desc');
		$manager->persist($error);
		$this->addReference('floating-holiday-error', $error);
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
