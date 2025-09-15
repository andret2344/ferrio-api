<?php

namespace App\Tests\Fixture;

use App\Entity\Language;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Override;

class LanguageFixture extends Fixture {
	#[Override]
	public function load(ObjectManager $manager): void {
		$languagePl = new Language('pl', 'Polski');
		$manager->persist($languagePl);
		$this->addReference('language-pl', $languagePl);

		$languageEn = new Language('en', 'English');
		$manager->persist($languageEn);
		$this->addReference('language-en', $languageEn);

		$manager->flush();
	}
}
