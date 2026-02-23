<?php

namespace App\Tests\Fixture;

use App\Entity\Script;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Override;

class ScriptFixture extends Fixture
{
	#[Override]
	public function load(ObjectManager $manager): void
	{
		$script = new Script(1, 'return \'01.01\'');
		$manager->persist($script);
		$this->addReference('script', $script);
		$manager->flush();
	}
}
