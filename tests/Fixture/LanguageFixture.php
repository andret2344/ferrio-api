<?php

namespace App\Tests\Fixture;

use App\Entity\Language;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Override;

class LanguageFixture extends Fixture
{
	public const string LANGUAGE_PL = 'language-pl';
	public const string LANGUAGE_EN = 'language-en';

	#[Override]
	public function load(ObjectManager $manager): void
	{
		$languagePl = new Language('pl', 'Polski');
		$manager->persist($languagePl);
		$this->addReference(self::LANGUAGE_PL, $languagePl);

		$languageEn = new Language('en', 'English');
		$manager->persist($languageEn);
		$this->addReference(self::LANGUAGE_EN, $languageEn);

		$manager->flush();
	}
}
