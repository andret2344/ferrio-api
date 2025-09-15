<?php

declare(strict_types=1);

namespace App\Tests\Fixture;

use App\Entity\FixedHolidaySuggestion;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Override;

class FixedHolidaySuggestionFixture extends Fixture {
	#[Override]
	public function load(ObjectManager $manager): void {
		$suggestion = new FixedHolidaySuggestion('user-id', 'Test name', 'Test description', 1, 1, new DateTimeImmutable("2024-06-06 20:30:40"));
		$manager->persist($suggestion);
		$this->addReference('fixed-holiday-suggestion', $suggestion);
		$manager->flush();
	}
}
