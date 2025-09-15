<?php

namespace App\Tests\Fixture;

use App\Entity\FloatingHolidayMetadata;
use App\Entity\Script;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

class FloatingHolidayMetadataFixture extends Fixture implements DependentFixtureInterface {
	#[Override]
	public function load(ObjectManager $manager): void {
		$script = $this->getReference('script', Script::class);
		$metadata = new FloatingHolidayMetadata(1, null, null, $script, '[]', false);
		$manager->persist($metadata);
		$this->addReference('floating-holiday-metadata', $metadata);
		$manager->flush();
	}

	#[Override]
	public function getDependencies(): array {
		return [
			ScriptFixture::class
		];
	}
}
