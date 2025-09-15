<?php

declare(strict_types=1);

namespace App\Tests\Fixture;

use App\Entity\FixedHolidayError;
use App\Entity\FixedHolidayMetadata;
use App\Entity\Language;
use App\Entity\ReportType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

class FixedHolidayErrorFixture extends Fixture implements DependentFixtureInterface {
	#[Override]
	public function load(ObjectManager $manager): void {
		$language = $this->getReference('language-en', Language::class);
		$metadata = $this->getReference('fixed-holiday-metadata', FixedHolidayMetadata::class);
		$error = new FixedHolidayError('user-id', $language, $metadata, ReportType::OTHER, 'Test desc');
		$manager->persist($error);
		$this->addReference('fixed-holiday-error', $error);
		$manager->flush();
	}

	#[Override]
	public function getDependencies(): array {
		return [
			LanguageFixture::class,
			FixedHolidayMetadataFixture::class,
		];
	}
}
