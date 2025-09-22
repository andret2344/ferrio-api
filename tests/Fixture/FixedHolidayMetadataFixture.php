<?php

namespace App\Tests\Fixture;

use App\Entity\FixedHolidayMetadata;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Override;

class FixedHolidayMetadataFixture extends Fixture
{
	const string METADATA_0301 = 'fixed-holiday-metadata-0301';
	const string METADATA_0314 = 'fixed-holiday-metadata-0314';

	#[Override]
	public function load(ObjectManager $manager): void
	{
		$metadata0301 = new FixedHolidayMetadata(3, 1, false, null, null, false);
		$manager->persist($metadata0301);
		$this->addReference(self::METADATA_0301, $metadata0301);

		$metadata0314 = new FixedHolidayMetadata(3, 14, true, null, null, true);
		$manager->persist($metadata0314);
		$this->addReference(self::METADATA_0314, $metadata0314);

		$manager->flush();
	}
}
