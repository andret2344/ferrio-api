<?php

namespace App\Tests\Fixture;

use App\Entity\FixedHolidayMetadata;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Override;

class FixedHolidayMetadataFixture extends Fixture {
	#[Override]
	public function load(ObjectManager $manager): void {
		$metadata = new FixedHolidayMetadata(1, 1, 0, null, null, false);
		$manager->persist($metadata);
		$this->addReference('fixed-holiday-metadata', $metadata);
		$manager->flush();
	}
}
