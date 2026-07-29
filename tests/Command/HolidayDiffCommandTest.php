<?php

namespace App\Tests\Command;

use App\Enum\HolidayDiffStatus;
use App\Tests\Fixture\CountryFixture;
use App\Tests\Fixture\PolishHolidayFixture;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class HolidayDiffCommandTest extends KernelTestCase
{
	private AbstractDatabaseTool $databaseTool;
	private CommandTester $tester;
	private string $file;

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
			PolishHolidayFixture::class,
		]);

		$application = new Application(static::$kernel);
		$this->tester = new CommandTester($application->find('app:holiday:diff'));
		$this->file = tempnam(sys_get_temp_dir(), 'holiday-diff-');
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
		if (is_file($this->file)) {
			unlink($this->file);
		}
	}

	public function testReportsEveryBucket(): void
	{
		$this->write([
			'1 marca - Dzień Pierwszy Marca',
			'5 marca - Dzień Pierwszy Marca',
			'3 kwietnia - Dzień Zupełnie Innego Wydarzenia',
		]);

		self::assertSame(Command::SUCCESS, $this->tester->execute(['file' => $this->file]));

		$display = $this->tester->getDisplay();
		self::assertStringContainsString(HolidayDiffStatus::DATE_MISMATCH->value, $display);
		self::assertStringContainsString(HolidayDiffStatus::MISSING->value, $display);
		self::assertStringContainsString('Dzień Zupełnie Innego Wydarzenia', $display);
	}

	public function testHidesExactRowsUnlessAsked(): void
	{
		$this->write(['1 marca - Dzień Pierwszy Marca']);

		$this->tester->execute(['file' => $this->file]);
		// The name shows up only in the summary count, never as a listed row.
		self::assertStringNotContainsString('Dzień Pierwszy Marca', $this->tester->getDisplay());

		$this->tester->execute(['file' => $this->file, '--all' => true]);
		self::assertStringContainsString('Dzień Pierwszy Marca', $this->tester->getDisplay());
	}

	public function testJsonOutputCarriesTheStoredMatch(): void
	{
		$this->write(['5 marca - Dzień Pierwszy Marca']);

		$this->tester->execute(['file' => $this->file, '--json' => true]);

		$rows = json_decode($this->tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
		self::assertCount(1, $rows);
		self::assertSame(HolidayDiffStatus::DATE_MISMATCH->value, $rows[0]['status']);
		self::assertSame(5, $rows[0]['day']);
		self::assertSame(3, $rows[0]['month']);
		self::assertSame(1, $rows[0]['stored_day']);
		self::assertSame('fixed', $rows[0]['stored_kind']);
		self::assertSame(PolishHolidayFixture::FIXED_0301_NAME, $rows[0]['stored_name']);
	}

	public function testFailsOnAnUnreadableFile(): void
	{
		$status = $this->tester->execute(['file' => sprintf('%s/does-not-exist', sys_get_temp_dir())]);

		self::assertSame(Command::FAILURE, $status);
		self::assertStringContainsString('Cannot read', $this->tester->getDisplay());
	}

	public function testWarnsWhenNothingParsed(): void
	{
		$this->write(['Poniżej lista najdziwniejszych świąt w roku.']);

		self::assertSame(Command::SUCCESS, $this->tester->execute(['file' => $this->file]));
		self::assertStringContainsString('No holiday lines', $this->tester->getDisplay());
	}

	/**
	 * @param string[] $lines
	 */
	private function write(array $lines): void
	{
		file_put_contents($this->file, implode("\r\n", $lines));
	}
}
