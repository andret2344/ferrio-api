<?php

namespace App\Tests\Fixture;

use App\Entity\AdminUser;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Override;

class AdminUserFixture extends Fixture
{
	public const string ADMIN = 'admin-user';

	#[Override]
	public function load(ObjectManager $manager): void
	{
		// The password hash is never verified: the tests authenticate with `loginUser`, which skips
		// the hasher entirely.
		$admin = new AdminUser(
			'admin@null.com',
			'admin',
			'not-a-real-hash',
			['ROLE_ADMIN'],
			new DateTimeImmutable('2024-01-01T00:00:00+000')
		);
		$manager->persist($admin);
		$this->addReference(self::ADMIN, $admin);
		$manager->flush();
	}
}
