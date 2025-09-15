<?php

declare(strict_types=1);

namespace App\Tests\Fixture;

use App\Entity\FloatingHolidaySuggestion;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Override;

class FloatingHolidaySuggestionFixture extends Fixture {
	#[Override]
	public function load(ObjectManager $manager): void {
		$error = new FloatingHolidaySuggestion('user-id', 'Test name', 'Test description', '01.01', new DateTimeImmutable("2024-06-06 20:30:40"));
		$manager->persist($error);
		$this->addReference('floating-holiday-suggestion', $error);
		$manager->flush();
	}
}
