<?php

namespace App\Tests\Fixture;

use App\Entity\Ban;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Override;

class BanFixture extends Fixture
{
	#[Override]
	public function load(ObjectManager $manager): void
	{
		$ban = new Ban('user-id-banned', 'Test ban', new DateTimeImmutable("2024-06-30T08:00:00+000"));
		$manager->persist($ban);
		$this->addReference('ban', $ban);
		$manager->flush();
	}
}
