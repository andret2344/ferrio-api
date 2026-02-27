<?php

namespace App\Tests\Fixture;

use App\Entity\Poll;
use App\Entity\PollOption;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Override;

class PollFixture extends Fixture
{
	#[Override]
	public function load(ObjectManager $manager): void
	{
		$now = new DateTimeImmutable();

		// Active poll
		$poll = new Poll('test-poll', 'Do you like this?', $now->modify('-1 day'), $now->modify('+1 day'));
		$yes = new PollOption($poll, 'Yes');
		$no = new PollOption($poll, 'No');
		$poll->addOption($yes);
		$poll->addOption($no);
		$manager->persist($poll);

		// Past poll (inactive)
		$past = new Poll('past-poll', 'Did you like that?', $now->modify('-10 days'), $now->modify('-5 days'));
		$pastYes = new PollOption($past, 'Yes');
		$pastNo = new PollOption($past, 'No');
		$past->addOption($pastYes);
		$past->addOption($pastNo);
		$manager->persist($past);

		$manager->flush();

		$this->addReference('active-poll', $poll);
		$this->addReference('option-yes', $yes);
		$this->addReference('option-no', $no);
		$this->addReference('past-poll', $past);
	}
}
