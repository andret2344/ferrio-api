<?php

namespace App\Tests\Fixture;

use App\Entity\FloatingHolidayMetadata;
use App\Entity\Script;
use App\Enum\Algorithm;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

class FloatingHolidayMetadataFixture extends Fixture implements DependentFixtureInterface
{
	#[Override]
	public function load(ObjectManager $manager): void
	{
		$script = $this->getReference('script', Script::class);
		$metadata = new FloatingHolidayMetadata(
			true,
			null,
			null,
			$script,
			json_encode([2026, 4]),
			false,
			Algorithm::HARDCODED_DATES,
			json_encode(['dates' => ['2026' => '15.4', '2025' => '14.4']]),
		);
		$manager->persist($metadata);
		$this->addReference('floating-holiday-metadata', $metadata);
		$manager->flush();
	}

	#[Override]
	public function getDependencies(): array
	{
		return [
			ScriptFixture::class
		];
	}
}
