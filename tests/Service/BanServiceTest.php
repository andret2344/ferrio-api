<?php

namespace App\Tests\Service;

use App\Entity\Ban;
use App\Tests\Fixture\BanFixture;
use DateTimeImmutable;
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

	public function testBanCreatesBan(): void
	{
		$before = new DateTimeImmutable('-1 second');
		$ban = $this->banService->ban('user-id-fresh', 'Spam');

		$this->assertSame('user-id-fresh', $ban->userId);
		$this->assertSame('Spam', $ban->reason);
		$this->assertGreaterThanOrEqual($before, $ban->datetime);
		$this->assertSame(2, $this->banService->count());
		$this->assertNotNull($this->banService->getBanInfo('user-id-fresh'));
	}

	public function testBanOfAlreadyBannedUserOverwritesReasonAndDate(): void
	{
		$original = $this->banService->getBanInfo('user-id-banned');
		$originalDate = $original->datetime;

		$ban = $this->banService->ban('user-id-banned', 'Repeat offender');

		$this->assertSame('Repeat offender', $ban->reason);
		$this->assertGreaterThan($originalDate, $ban->datetime);
		$this->assertSame(1, $this->banService->count());
	}

	public function testUnbanRemovesBan(): void
	{
		$this->assertTrue($this->banService->unban('user-id-banned'));

		$this->assertNull($this->banService->getBanInfo('user-id-banned'));
		$this->assertSame(0, $this->banService->count());
	}

	public function testUnbanOfNotBannedUserReturnsFalse(): void
	{
		$this->assertFalse($this->banService->unban('user-id-not-banned'));
		$this->assertSame(1, $this->banService->count());
	}

	public function testGetBansIsKeyedByUserIdAndSkipsUnbannedUsers(): void
	{
		$this->banService->ban('user-id-fresh', 'Spam');

		$bans = $this->banService->getBans(['user-id-banned', 'user-id-fresh', 'user-id-not-banned']);

		$this->assertCount(2, $bans);
		$this->assertArrayNotHasKey('user-id-not-banned', $bans);
		$this->assertSame('Test ban', $bans['user-id-banned']->reason);
		$this->assertSame('Spam', $bans['user-id-fresh']->reason);
	}

	public function testGetBansOfEmptyInput(): void
	{
		$this->assertSame([], $this->banService->getBans([]));
		$this->assertSame([], $this->banService->getBans(['']));
	}

	public function testFindAllReturnsNewestBanFirst(): void
	{
		$this->banService->ban('user-id-fresh', 'Spam');

		$bans = $this->banService->findAll();

		$this->assertCount(2, $bans);
		$this->assertSame('user-id-fresh', $bans[0]->userId);
		$this->assertSame('user-id-banned', $bans[1]->userId);
	}
}
