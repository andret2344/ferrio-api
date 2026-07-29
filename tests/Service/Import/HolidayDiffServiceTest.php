<?php

namespace App\Tests\Service\Import;

use App\DTO\HolidayCandidate;
use App\Enum\HolidayDiffStatus;
use App\Service\Import\HolidayDiffService;
use App\Service\Import\HolidayTextParser;
use App\Tests\Fixture\CountryFixture;
use App\Tests\Fixture\FixedHolidayFixture;
use App\Tests\Fixture\FloatingHolidayFixture;
use App\Tests\Fixture\PolishHolidayFixture;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class HolidayDiffServiceTest extends KernelTestCase
{
	private HolidayDiffService $diff;
	private HolidayTextParser $parser;
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
			// The English rows are loaded on purpose: the diff must ignore every language but Polish.
			FixedHolidayFixture::class,
			FloatingHolidayFixture::class,
			PolishHolidayFixture::class,
		]);

		$this->diff = static::getContainer()->get(HolidayDiffService::class);
		$this->parser = new HolidayTextParser();
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
	}

	public function testClassifiesEveryKindOfLine(): void
	{
		// The stored rows are 01.03 "Dzień Pierwszy Marca", 14.03 "Dzień Liczby Pi" and the floating
		// "Święto Ruchome".
		$text = implode("\r\n", [
			'1 marca - Dzień Pierwszy Marca',
			'5 marca - Dzień Pierwszy Marca',
			'14 marca - Międzynarodowy Dzień Liczby Pi',
			'3 kwietnia - Dzień Zupełnie Innego Wydarzenia',
			'7 lipca - Święto Ruchome',
			'20 maja - Dzień Liczby Pi i Matematyki',
			'32 stycznia - Święto Nieistniejące',
		]);

		$rows = $this->diff->diff($this->parser->parse($text));

		self::assertCount(7, $rows);
		self::assertSame(HolidayDiffStatus::EXACT, $rows[0]->status);
		self::assertSame(HolidayDiffStatus::DATE_MISMATCH, $rows[1]->status);
		self::assertSame(HolidayDiffStatus::EXACT, $rows[2]->status);
		self::assertSame(HolidayDiffStatus::MISSING, $rows[3]->status);
		self::assertSame(HolidayDiffStatus::FLOATING_MATCH, $rows[4]->status);
		self::assertSame(HolidayDiffStatus::AMBIGUOUS, $rows[5]->status);
		self::assertSame(HolidayDiffStatus::UNPARSED, $rows[6]->status);
	}

	public function testDateMismatchCarriesTheStoredDate(): void
	{
		$rows = $this->diff->diff($this->parser->parse('5 marca - Dzień Pierwszy Marca'));

		self::assertSame(HolidayDiffStatus::DATE_MISMATCH, $rows[0]->status);
		self::assertNotNull($rows[0]->candidate);
		self::assertSame(PolishHolidayFixture::FIXED_0301_NAME, $rows[0]->candidate->name);
		self::assertSame([1, 3], [$rows[0]->candidate->day, $rows[0]->candidate->month]);
	}

	public function testMissingCarriesNoCandidate(): void
	{
		$rows = $this->diff->diff($this->parser->parse('3 kwietnia - Dzień Zupełnie Innego Wydarzenia'));

		self::assertSame(HolidayDiffStatus::MISSING, $rows[0]->status);
		self::assertNull($rows[0]->candidate);
	}

	public function testFloatingHolidaysAreInTheCandidatePool(): void
	{
		// Without them, every floating holiday a source pins to a concrete date would come back as
		// MISSING and acting on that would create a duplicate.
		$kinds = array_map(
			static fn(HolidayCandidate $candidate): string => $candidate->kind,
			$this->diff->candidates()
		);

		self::assertContains('floating', $kinds);
		self::assertContains('fixed', $kinds);
	}

	public function testIgnoresNonPolishHolidays(): void
	{
		// Only the Polish source language is compared; an English name must not shadow a Polish one.
		$names = array_map(
			static fn(HolidayCandidate $candidate): string => $candidate->name,
			$this->diff->candidates()
		);

		self::assertEqualsCanonicalizing([
			PolishHolidayFixture::FIXED_0301_NAME,
			PolishHolidayFixture::FIXED_0314_NAME,
			PolishHolidayFixture::FLOATING_NAME,
		], $names);
	}

	public function testUnparsedRowsAreNotMatched(): void
	{
		$rows = $this->diff->diff($this->parser->parse('32 stycznia - Dzień Pierwszy Marca'));

		self::assertSame(HolidayDiffStatus::UNPARSED, $rows[0]->status);
		self::assertNull($rows[0]->candidate);
		self::assertSame(0.0, $rows[0]->score);
	}

	public function testEmptyInputProducesNoRows(): void
	{
		self::assertSame([], $this->diff->diff($this->parser->parse('')));
	}
}
