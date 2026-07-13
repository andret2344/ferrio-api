<?php

namespace App\Tests\Service;

use App\Entity\Country;
use App\Entity\FixedHolidaySuggestion;
use App\Entity\Poll;
use App\Entity\PollOption;
use App\Entity\PollVote;
use App\Entity\ReportState;
use App\Service\AdminUserService;
use App\Tests\Fixture\CountryFixture;
use App\Tests\Fixture\FixedHolidayErrorFixture;
use App\Tests\Fixture\FixedHolidaySuggestionFixture;
use App\Tests\Fixture\FloatingHolidayErrorFixture;
use App\Tests\Fixture\FloatingHolidaySuggestionFixture;
use App\Tests\Fixture\PollFixture;
use DateTimeImmutable;
use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AdminUserServiceTest extends KernelTestCase
{
	/** Reporter of every fixture report: 4 reports (one per kind), all REPORTED, last one is "now". */
	private const string BUSY = 'user-id';

	/** Added below: a single moderated (APPLIED) report, dated far enough back to count as inactive. */
	private const string STALE = 'user-stale';

	private AdminUserService $service;
	private EntityManagerInterface $em;
	private AbstractDatabaseTool $databaseTool;
	private AbstractExecutor $fixtures;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();
		self::bootKernel();

		$this->databaseTool = static::getContainer()
			->get(DatabaseToolCollection::class)
			->get();
		$this->fixtures = $this->databaseTool->loadFixtures([
			CountryFixture::class,
			FixedHolidaySuggestionFixture::class,
			FloatingHolidaySuggestionFixture::class,
			FixedHolidayErrorFixture::class,
			FloatingHolidayErrorFixture::class,
			PollFixture::class,
		]);

		$this->em = static::getContainer()->get(EntityManagerInterface::class);
		$this->service = static::getContainer()->get(AdminUserService::class);

		$country = $this->fixtures->getReferenceRepository()
			->getReference('country-gb', Country::class);
		$this->em->persist(new FixedHolidaySuggestion(
			self::STALE,
			'Old name',
			'Old description',
			2,
			2,
			$country,
			new DateTimeImmutable('2020-01-01 10:00:00'),
			ReportState::APPLIED,
		));

		$poll = $this->fixtures->getReferenceRepository()
			->getReference('active-poll', Poll::class);
		$option = $this->fixtures->getReferenceRepository()
			->getReference('option-yes', PollOption::class);
		$this->em->persist(new PollVote(self::BUSY, $option, $poll));
		$this->em->flush();
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
	}

	public function testReportersAreAggregatedPerKindAndSortedByTotal(): void
	{
		$reporters = $this->service->reporters();

		$this->assertCount(2, $reporters);
		$this->assertSame(self::BUSY, $reporters[0]['user_id']);
		$this->assertSame(4, $reporters[0]['total']);
		$this->assertSame(4, $reporters[0]['pending']);
		$this->assertSame([
			'fixed_suggestion' => 1,
			'floating_suggestion' => 1,
			'fixed_error' => 1,
			'floating_error' => 1,
		], $reporters[0]['counts']);

		$this->assertSame(self::STALE, $reporters[1]['user_id']);
		$this->assertSame(1, $reporters[1]['total']);
		$this->assertSame(0, $reporters[1]['pending']);
		$this->assertSame(1, $reporters[1]['counts']['fixed_suggestion']);
		$this->assertSame(0, $reporters[1]['counts']['floating_error']);
	}

	public function testReportersCarryTheMostRecentReportDate(): void
	{
		$reporters = $this->service->reporters();
		$byUser = array_column($reporters, null, 'user_id');

		$this->assertInstanceOf(DateTimeImmutable::class, $byUser[self::BUSY]['last']);
		// The error fixtures are created with "now", which must win over the 2024 suggestion fixtures.
		$this->assertGreaterThan(new DateTimeImmutable('-1 hour'), $byUser[self::BUSY]['last']);
		$this->assertSame('2020-01-01 10:00:00', $byUser[self::STALE]['last']->format('Y-m-d H:i:s'));
	}

	public function testTotals(): void
	{
		$totals = $this->service->totals($this->service->reporters(), 7);

		$this->assertSame(2, $totals['reporters']);
		$this->assertSame(1, $totals['active']);
		$this->assertSame(5, $totals['reports']);
		$this->assertSame(4, $totals['pending']);
		$this->assertSame(1, $totals['votes']);
		$this->assertSame(7, $totals['banned']);
	}

	public function testTotalsOfNoReporters(): void
	{
		$totals = $this->service->totals([], 0);

		$this->assertSame(0, $totals['reporters']);
		$this->assertSame(0, $totals['active']);
		$this->assertSame(0, $totals['reports']);
		$this->assertSame(0, $totals['pending']);
	}

	public function testReportCounts(): void
	{
		$counts = $this->service->reportCounts([self::BUSY, self::STALE, 'user-unknown']);

		$this->assertSame(['total' => 4, 'pending' => 4], $counts[self::BUSY]);
		$this->assertSame(['total' => 1, 'pending' => 0], $counts[self::STALE]);
		$this->assertArrayNotHasKey('user-unknown', $counts);
	}

	public function testReportCountsOfEmptyInput(): void
	{
		$this->assertSame([], $this->service->reportCounts([]));
		$this->assertSame([], $this->service->reportCounts(['', null]));
	}

	public function testDeletePendingReportsKeepsModeratedOnes(): void
	{
		$deleted = $this->service->deleteReports(self::BUSY, AdminUserService::SCOPE_PENDING);

		$this->assertSame(4, $deleted);
		$this->assertSame([], $this->service->reportCounts([self::BUSY]));
		// Another user's reports are untouched.
		$this->assertSame(['total' => 1, 'pending' => 0], $this->service->reportCounts([self::STALE])[self::STALE]);
	}

	public function testDeletePendingReportsSkipsModeratedReportsOfTheSameUser(): void
	{
		$deleted = $this->service->deleteReports(self::STALE, AdminUserService::SCOPE_PENDING);

		$this->assertSame(0, $deleted);
		$this->assertSame(['total' => 1, 'pending' => 0], $this->service->reportCounts([self::STALE])[self::STALE]);
	}

	public function testDeleteAllReports(): void
	{
		$deleted = $this->service->deleteReports(self::STALE, AdminUserService::SCOPE_ALL);

		$this->assertSame(1, $deleted);
		$this->assertSame([], $this->service->reportCounts([self::STALE]));
	}

	public function testDeleteReportsOfUnknownUser(): void
	{
		$this->assertSame(0, $this->service->deleteReports('user-unknown', AdminUserService::SCOPE_ALL));
		$this->assertSame(5, $this->service->totals($this->service->reporters(), 0)['reports']);
	}
}
