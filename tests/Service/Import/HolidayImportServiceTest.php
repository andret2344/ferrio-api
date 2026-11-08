<?php

namespace App\Tests\Service\Import;

use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use App\Entity\HolidayImportCandidate;
use App\Entity\Language;
use App\Enum\BlacklistReason;
use App\Enum\ImportCandidateStatus;
use App\Service\Import\HolidayImportService;
use App\Tests\Fixture\CountryFixture;
use App\Tests\Fixture\FixedHolidayFixture;
use App\Tests\Fixture\FloatingHolidayFixture;
use App\Tests\Fixture\PolishHolidayFixture;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The stored Polish rows are 01.03 "Dzień Pierwszy Marca", 14.03 "Dzień Liczby Pi" and the floating
 * "Święto Ruchome" (PolishHolidayFixture).
 */
class HolidayImportServiceTest extends KernelTestCase
{
	private HolidayImportService $import;
	private EntityManagerInterface $entityManager;
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
			FixedHolidayFixture::class,
			FloatingHolidayFixture::class,
			PolishHolidayFixture::class,
		]);

		$this->import = static::getContainer()->get(HolidayImportService::class);
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
	}

	public function testOnlyUnsettledLinesBecomeTiles(): void
	{
		$result = $this->import->import(implode("\r\n", [
			'1 marca - Dzień Pierwszy Marca',
			'7 lipca - Święto Ruchome',
			'3 kwietnia - Dzień Zupełnie Innego Wydarzenia',
			'32 stycznia - Święto Nieistniejące',
		]));

		self::assertSame(1, $result->created);
		self::assertSame(0, $result->merged);
		self::assertSame(2, $result->alreadyStored);
		self::assertSame([['line' => 4, 'text' => '32 stycznia - Święto Nieistniejące']], $result->unparsed);

		$tiles = $this->import->tiles();
		self::assertCount(1, $tiles);
		self::assertSame(ImportCandidateStatus::MISSING, $tiles[0]->status);
		self::assertSame('Dzień Zupełnie Innego Wydarzenia', $tiles[0]->candidate->name);
	}

	public function testRewordedNameFromAnotherPasteLandsOnTheSameTile(): void
	{
		$this->import->import('3 kwietnia - Dzień Kota');
		$result = $this->import->import('3 kwietnia - Światowy Dzień Kotów');

		self::assertSame(0, $result->created);
		self::assertSame(1, $result->merged);
		$tiles = $this->import->tiles();
		self::assertCount(1, $tiles);
		self::assertSame('Dzień Kota', $tiles[0]->candidate->name);
		self::assertSame(['3.4' => 2], $tiles[0]->candidate->dates);
	}

	public function testDateCountsOncePerPaste(): void
	{
		// A source listing the same holiday twice is still one source.
		$result = $this->import->import("3 kwietnia - Dzień Kota\r\n3 kwietnia - Dzień Kotów");

		self::assertSame(1, $result->created);
		self::assertSame(1, $result->merged);
		self::assertSame(['3.4' => 1], $this->import->tiles()[0]->candidate->dates);
	}

	public function testDisagreeingDatesStayOnOneTile(): void
	{
		$this->import->import("3 kwietnia - Dzień Kota\r\n4 kwietnia - Dzień Kota");
		$this->import->import('4 kwietnia - Dzień Kota');

		$tiles = $this->import->tiles();
		self::assertCount(1, $tiles);
		self::assertSame(
			[['day' => 4, 'month' => 4, 'count' => 2], ['day' => 3, 'month' => 4, 'count' => 1]],
			$tiles[0]->candidate->sortedDates()
		);
	}

	public function testRejectedNameAndItsVariantsAreSkippedAndReported(): void
	{
		$this->import->import('3 kwietnia - Dzień Kota');
		$this->import->reject($this->import->tiles()[0]->candidate, BlacklistReason::INVENTED, null);

		$result = $this->import->import('3 kwietnia - Światowy Dzień Kotów');

		self::assertSame(0, $result->created);
		self::assertSame([[
			'name' => 'Światowy Dzień Kotów',
			'rejected_as' => 'Dzień Kota',
			'reason' => BlacklistReason::INVENTED->label(),
		]], $result->blacklisted);
		self::assertSame([], $this->import->tiles());
	}

	public function testOtherReasonNeedsAComment(): void
	{
		$this->import->import('3 kwietnia - Dzień Kota');
		$candidate = $this->import->tiles()[0]->candidate;

		$this->expectException(InvalidArgumentException::class);
		$this->import->reject($candidate, BlacklistReason::OTHER, '   ');
	}

	public function testIgnoredDateConflictStaysIgnoredUntilANewDateArrives(): void
	{
		$this->import->import('5 marca - Dzień Pierwszy Marca');
		$tile = $this->import->tiles()[0];
		self::assertSame(ImportCandidateStatus::DATE_MISMATCH, $tile->status);
		self::assertSame(PolishHolidayFixture::FIXED_0301_NAME, $tile->match?->name);
		self::assertSame([['day' => 5, 'month' => 3, 'count' => 1]], $tile->conflictingDates);

		$this->import->ignoreConflicts($tile->candidate);
		self::assertSame([], $this->import->tiles());

		// The same conflict again: still ignored, and the row is kept for that decision.
		$this->import->import('5 marca - Dzień Pierwszy Marca');
		self::assertSame([], $this->import->tiles());

		// A date nobody gave before is new information.
		$this->import->import('6 marca - Dzień Pierwszy Marca');
		$tiles = $this->import->tiles();
		self::assertCount(1, $tiles);
		self::assertSame([['day' => 6, 'month' => 3, 'count' => 1]], $tiles[0]->conflictingDates);
	}

	public function testCloseNameOffersStoredHolidaysAndALinkSettlesIt(): void
	{
		$this->import->import('14 marca - Dzień Liczby Pi i Matematyki');
		$tile = $this->import->tiles()[0];

		self::assertSame(ImportCandidateStatus::AMBIGUOUS, $tile->status);
		self::assertNotEmpty($tile->alternatives);
		$stored = $tile->alternatives[0]['candidate'];
		self::assertSame(PolishHolidayFixture::FIXED_0314_NAME, $stored->name);

		$this->import->link($tile->candidate, $stored->kind, $stored->metadataId);

		// Same date as the stored holiday, so nothing is left to decide - and the alias is remembered.
		self::assertSame([], $this->import->tiles());
		$this->import->import('14 marca - Dzień Liczby Pi i Matematyki');
		self::assertSame([], $this->import->tiles());
	}

	public function testLinkToAMissingHolidayIsRefused(): void
	{
		$this->import->import('3 kwietnia - Dzień Kota');

		$this->expectException(InvalidArgumentException::class);
		$this->import->link($this->import->tiles()[0]->candidate, 'fixed', 999999);
	}

	public function testTileDisappearsOnceItsHolidayIsStoredAndTheNextPastePrunesIt(): void
	{
		$this->import->import('3 kwietnia - Dzień Zupełnie Innego Wydarzenia');
		self::assertCount(1, $this->import->tiles());

		$polish = $this->entityManager->getRepository(Language::class)->find(Language::DEFAULT_CODE);
		$metadata = new FixedHolidayMetadata(4, 3, false, null, [], false);
		$this->entityManager->persist($metadata);
		$this->entityManager->persist(new FixedHoliday($polish, $metadata, 'Dzień Zupełnie Innego Wydarzenia'));
		$this->entityManager->flush();

		self::assertSame([], $this->import->tiles());

		$this->import->import('3 kwietnia - Dzień Kota');
		$names = array_map(
			fn(HolidayImportCandidate $candidate) => $candidate->name,
			$this->entityManager->getRepository(HolidayImportCandidate::class)->findAll()
		);
		self::assertSame(['Dzień Kota'], $names);
	}

	public function testRemovedTileComesBackOnTheNextPaste(): void
	{
		$this->import->import('3 kwietnia - Dzień Kota');
		$this->import->remove($this->import->tiles()[0]->candidate);
		self::assertSame([], $this->import->tiles());

		$this->import->import('3 kwietnia - Dzień Kota');
		self::assertCount(1, $this->import->tiles());
	}

	public function testTilesAreInCalendarOrder(): void
	{
		$this->import->import("20 grudnia - Dzień Kota\r\n2 lutego - Dzień Psa\r\n2 lutego - Dzień Chomika");

		$names = array_map(fn($tile) => $tile->candidate->name, $this->import->tiles());
		self::assertSame(['Dzień Chomika', 'Dzień Psa', 'Dzień Kota'], $names);
	}
}
