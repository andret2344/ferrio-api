<?php

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use App\Entity\Language;
use App\Tests\Fixture\AdminUserFixture;
use App\Tests\Fixture\CountryFixture;
use App\Tests\Fixture\FixedHolidayErrorFixture;
use App\Tests\Fixture\FixedHolidayFixture;
use App\Tests\Fixture\FixedHolidayMetadataFixture;
use App\Tests\Fixture\FixedHolidaySuggestionFixture;
use App\Tests\Fixture\FloatingHolidayFixture;
use App\Tests\Fixture\LanguageFixture;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminStatsControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private AbstractDatabaseTool $databaseTool;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->client = static::createClient();
		$this->databaseTool = static::getContainer()
			->get(DatabaseToolCollection::class)
			->get();
		$fixtures = $this->databaseTool->loadFixtures([
			AdminUserFixture::class,
			CountryFixture::class,
			FixedHolidayFixture::class,
			FloatingHolidayFixture::class,
			FixedHolidaySuggestionFixture::class,
			FixedHolidayErrorFixture::class,
		]);

		// Coverage is measured against the Polish source rows, which the shared fixtures do not
		// create, and a language nobody translated into is what makes "missing" non-zero.
		$em = static::getContainer()->get(EntityManagerInterface::class);
		$polish = $fixtures->getReferenceRepository()
			->getReference(LanguageFixture::LANGUAGE_PL, Language::class);
		$german = new Language('de', 'Deutsch');
		$em->persist($german);
		foreach ([FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadataFixture::METADATA_0314] as $reference) {
			$metadata = $fixtures->getReferenceRepository()
				->getReference($reference, FixedHolidayMetadata::class);
			$em->persist(new FixedHoliday($polish, $metadata, 'Polskie święto', 'Opis', null));
		}
		$em->flush();

		$this->client->loginUser($fixtures->getReferenceRepository()
			->getReference(AdminUserFixture::ADMIN, AdminUser::class));
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
	}

	public function testApiPageRendersWithoutHits(): void
	{
		$this->client->request('GET', '/admin/stats');

		$this->assertResponseIsSuccessful();
		$this->assertSelectorTextContains('.manage-content', 'No API hits recorded in this range.');
	}

	public function testHolidayStatsPageChartsCarryTheirData(): void
	{
		$crawler = $this->client->request('GET', '/admin/stats/holidays');

		$this->assertResponseIsSuccessful();

		$coverage = json_decode($crawler->filter('#coverage-data')->text(), true);
		$this->assertSame('stacked_bar', $coverage['kind']);
		$this->assertSame(['Fixed translated', 'Floating translated', 'Missing'], array_column($coverage['series'], 'label'));
		// One bar per target language — Polish is the source, so it is not a translation target — and
		// axis labels are ICU display names, not the codes.
		$this->assertSame(['German', 'English'], $coverage['labels']);
		// English translates both Polish fixed holidays; German translates neither.
		$this->assertSame([0, 2], $coverage['series'][0]['data']);
		$this->assertSame([2, 0], $coverage['series'][2]['data']);

		$months = json_decode($crawler->filter('#months-data')->text(), true);
		$this->assertSame('January', $months['labels'][0]);
		$this->assertSame('December', $months['labels'][11]);
		$this->assertCount(12, $months['series'][0]['data']);

		$algorithms = json_decode($crawler->filter('#algorithms-data')->text(), true);
		$this->assertSame('horizontal_bar', $algorithms['kind']);
		// Human labels, not the snake_case enum values.
		$this->assertSame(['Hardcoded dates'], $algorithms['labels']);
	}

	public function testHolidayStatsPageLinksToTheMissingTranslations(): void
	{
		$crawler = $this->client->request('GET', '/admin/stats/holidays');

		$this->assertResponseIsSuccessful();
		$this->assertSame(1, $crawler->filter('a[href="/admin/missing/fixed/de"]')->count());
		// English is complete on the fixed side, so its cell is plain text rather than a drill-down.
		$this->assertSame(0, $crawler->filter('a[href="/admin/missing/fixed/en"]')->count());
	}

	public function testUserStatsPageChartsCarryTheirData(): void
	{
		$crawler = $this->client->request('GET', '/admin/stats/users');

		$this->assertResponseIsSuccessful();

		$kinds = json_decode($crawler->filter('#kinds-data')->text(), true);
		$this->assertSame('doughnut', $kinds['kind']);
		// One fixed suggestion + one fixed error, no floating reports.
		$this->assertSame([1, 0, 1, 0], $kinds['series'][0]['data']);

		$top = json_decode($crawler->filter('#top-reporters-data')->text(), true);
		$this->assertSame([2], $top['series'][0]['data']);
		// The axis carries the reporter's name and the e-mail rides along as a hover-only note; the
		// test Firebase double knows neither, so the label falls back to the UID and the note is empty.
		$this->assertSame(['user-id'], $top['labels']);
		$this->assertSame([''], $top['notes']);

		$timeline = json_decode($crawler->filter('#timeline-data')->text(), true);
		$this->assertSame('line', $timeline['kind']);
		$this->assertSame(array_sum($timeline['series'][0]['data']), 2);
	}

	public function testStatsPagesRequireAuthentication(): void
	{
		// Drops the session cookie `loginUser` set in setUp, so these run as an anonymous visitor.
		$this->client->getCookieJar()->clear();

		foreach (['/admin/stats', '/admin/stats/holidays', '/admin/stats/users'] as $url) {
			$this->client->request('GET', $url);
			// With form_login there is no Basic Auth challenge: the entry point redirects to `/`.
			$this->assertResponseRedirects('http://localhost/', null, $url);
		}
	}
}
