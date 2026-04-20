<?php

namespace App\Tests\Service;

use App\Entity\Ban;
use App\Tests\Fixture\BanFixture;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Service\BanService;

class BanServiceTest extends KernelTestCase
{
	private BanService $banService;
	private AbstractDatabaseTool $databaseTool;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();
		self::bootKernel();

		$this->databaseTool = static::getContainer()
			->get(DatabaseToolCollection::class)
			->get();

		$this->databaseTool->loadFixtures([BanFixture::class]);

		$this->banService = static::getContainer()->get(BanService::class);
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
	}

	public function testGetBanInfoReturnsBanForBannedUser(): void
	{
		$ban = $this->banService->getBanInfo('user-id-banned');

		$this->assertInstanceOf(Ban::class, $ban);
		$this->assertSame('user-id-banned', $ban->userId);
		$this->assertSame('Test ban', $ban->reason);
	}

	public function testGetBanInfoReturnsNullForNonBannedUser(): void
	{
		$ban = $this->banService->getBanInfo('user-id-not-banned');

		$this->assertNull($ban);
	}

	public function testGetBanInfoReturnsNullForEmptyUserId(): void
	{
		$ban = $this->banService->getBanInfo('');

		$this->assertNull($ban);
	}
}
