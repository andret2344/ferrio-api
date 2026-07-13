<?php

namespace App\Tests\Service;

use App\Service\AdminMetricsService;
use App\Tests\Fixture\CountryFixture;
use App\Tests\Fixture\FixedHolidayMetadataFixture;
use App\Tests\Fixture\FloatingHolidayMetadataFixture;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AdminMetricsServiceTest extends KernelTestCase
{
	private AdminMetricsService $metrics;
	private AbstractDatabaseTool $databaseTool;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();
		self::bootKernel();

		$this->databaseTool = static::getContainer()
			->get(DatabaseToolCollection::class)
			->get();
		$this->databaseTool->loadFixtures([
			CountryFixture::class,
			FixedHolidayMetadataFixture::class,
			FloatingHolidayMetadataFixture::class,
		]);

		$this->metrics = static::getContainer()->get(AdminMetricsService::class);
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
	}

	public function testFixedCountsByMonthKeepsEmptyMonths(): void
	{
		$counts = $this->metrics->fixedCountsByMonth();

		$this->assertSame(range(1, 12), array_keys($counts));
		// Both fixture holidays sit in March.
		$this->assertSame(2, $counts[3]);
		$this->assertSame(0, $counts[1]);
		$this->assertSame(0, $counts[12]);
	}

	public function testFloatingCountsByAlgorithm(): void
	{
		$counts = $this->metrics->floatingCountsByAlgorithm();

		$this->assertSame(['hardcoded_dates' => 1], $counts);
	}
}
