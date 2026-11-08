<?php

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use App\Entity\HolidayBlacklistEntry;
use App\Entity\HolidayImportCandidate;
use App\Tests\Fixture\AdminUserFixture;
use App\Tests\Fixture\CountryFixture;
use App\Tests\Fixture\FixedHolidayFixture;
use App\Tests\Fixture\FloatingHolidayFixture;
use App\Tests\Fixture\PolishHolidayFixture;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AdminImportControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private AbstractDatabaseTool $databaseTool;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->client = static::createClient();
		// Keeps one container across requests, so the mocked HTTP client survives the token GET.
		$this->client->disableReboot();
		$this->databaseTool = static::getContainer()
			->get(DatabaseToolCollection::class)
			->get();
		$fixtures = $this->databaseTool->loadFixtures([
			AdminUserFixture::class,
			CountryFixture::class,
			FixedHolidayFixture::class,
			FloatingHolidayFixture::class,
			PolishHolidayFixture::class,
		]);
		$this->client->loginUser($fixtures->getReferenceRepository()
			->getReference(AdminUserFixture::ADMIN, AdminUser::class));
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
	}

	public function testEmptyPageOffersThePasteForm(): void
	{
		$crawler = $this->client->request('GET', '/admin/import');

		$this->assertResponseIsSuccessful();
		self::assertCount(1, $crawler->filter('form textarea[name="text"]'));
		self::assertCount(1, $crawler->filter('[data-import-empty]'));
	}

	public function testPasteShowsTheSummaryAndTheTiles(): void
	{
		$this->paste(implode("\n", [
			'1 marca - Dzień Pierwszy Marca',
			'3 kwietnia - Dzień Kota',
			'5 marca - Dzień Pierwszy Marca',
			'32 stycznia - Święto Nieistniejące',
		]));

		$this->assertResponseRedirects('/admin/import');
		$crawler = $this->client->followRedirect();
		$this->assertResponseIsSuccessful();

		self::assertStringContainsString('2 new tiles', preg_replace('/\s+/', ' ', $crawler->filter('.import-result')->text()));
		self::assertCount(2, $crawler->filter('[data-import-tile]'));
		self::assertCount(1, $crawler->filter('.import-tile-missing'));
		self::assertCount(1, $crawler->filter('.import-tile-date_mismatch'));
		self::assertStringContainsString('32 stycznia', $crawler->filter('#import-unparsed')->text());

		// "Add" carries everything the create form can prefill.
		$add = $crawler->filter('.import-tile-missing a.btn-success')->attr('href');
		self::assertStringContainsString('/admin/holiday/fixed/new?', $add);
		self::assertStringContainsString('day=3', $add);
		self::assertStringContainsString('month=4', $add);
	}

	public function testTilesSurviveLeavingThePage(): void
	{
		$this->paste('3 kwietnia - Dzień Kota');
		$this->client->followRedirect();
		$this->client->request('GET', '/admin/stats/holidays');

		$crawler = $this->client->request('GET', '/admin/import');

		// The tile is still there; the paste summary was shown once and is gone.
		self::assertCount(1, $crawler->filter('[data-import-tile]'));
		self::assertCount(0, $crawler->filter('.import-result'));
	}

	public function testEmptyPasteIsRefused(): void
	{
		$this->paste('   ');

		$crawler = $this->client->followRedirect();
		self::assertStringContainsString('Paste some text first.', $crawler->filter('.alert-danger')->text());
	}

	public function testRemoveDropsTheTile(): void
	{
		$tile = $this->tileFor('3 kwietnia - Dzień Kota');

		$this->postJson(sprintf('/admin/api/import/%d/remove', $tile), []);

		$this->assertResponseIsSuccessful();
		self::assertNull($this->json()['html']);
		self::assertNull($this->entityManager()->getRepository(HolidayImportCandidate::class)->find($tile));
	}

	public function testRejectNeedsACommentForOtherAndThenLandsOnTheBlacklist(): void
	{
		$tile = $this->tileFor('3 kwietnia - Dzień Kota');

		$this->postJson(sprintf('/admin/api/import/%d/reject', $tile), ['reason' => 'other', 'comment' => '']);
		$this->assertResponseStatusCodeSame(400);

		$this->postJson(sprintf('/admin/api/import/%d/reject', $tile), ['reason' => 'other', 'comment' => 'Reklama sklepu']);
		$this->assertResponseIsSuccessful();
		self::assertNull($this->json()['html']);

		$crawler = $this->client->request('GET', '/admin/import/blacklist');
		$this->assertResponseIsSuccessful();
		self::assertStringContainsString('Dzień Kota', $crawler->filter('[data-blacklist-row]')->text());
		self::assertStringContainsString('Reklama sklepu', $crawler->filter('[data-blacklist-row]')->text());
	}

	public function testRestoreRemovesTheBlacklistEntry(): void
	{
		$tile = $this->tileFor('3 kwietnia - Dzień Kota');
		$this->postJson(sprintf('/admin/api/import/%d/reject', $tile), ['reason' => 'invented']);
		$entry = $this->entityManager()->getRepository(HolidayBlacklistEntry::class)->findOneBy(['name' => 'Dzień Kota']);
		self::assertNotNull($entry);

		$this->postJson(sprintf('/admin/api/import/blacklist/%d/restore', $entry->id), []);

		$this->assertResponseIsSuccessful();
		$this->entityManager()->clear();
		self::assertNull($this->entityManager()->getRepository(HolidayBlacklistEntry::class)->findOneBy(['name' => 'Dzień Kota']));
	}

	public function testIgnoreHidesTheDateConflict(): void
	{
		$tile = $this->tileFor('5 marca - Dzień Pierwszy Marca');

		$this->postJson(sprintf('/admin/api/import/%d/ignore', $tile), []);

		$this->assertResponseIsSuccessful();
		self::assertNull($this->json()['html']);
	}

	public function testVerifyStoresAndRendersTheVerdict(): void
	{
		$tile = $this->tileFor('3 kwietnia - Dzień Kota');
		static::getContainer()->set(HttpClientInterface::class, new MockHttpClient(new MockResponse(json_encode([
			'stop_reason' => 'end_turn',
			'content' => [
				['type' => 'web_search_tool_result', 'content' => [
					['type' => 'web_search_result', 'url' => 'https://blog.example/kot', 'title' => 'Kot'],
				]],
				['type' => 'text', 'text' => json_encode([
					'exists' => 'no',
					'confidence' => 'low',
					'summary' => 'Tylko kopie list z blogów.',
					'sources' => [['url' => 'https://blog.example/kot', 'kind' => 'calendar_blog']],
				])],
			],
		]))));

		$this->postJson(sprintf('/admin/api/import/%d/verify', $tile), []);

		$this->assertResponseIsSuccessful();
		$html = (string)$this->json()['html'];
		self::assertStringContainsString('Tylko kopie list z blogów.', $html);
		self::assertStringContainsString('Looks invented', $html);
		self::assertStringContainsString('blog.example', $html);

		$this->entityManager()->clear();
		$stored = $this->entityManager()->getRepository(HolidayImportCandidate::class)->find($tile);
		self::assertSame('no', $stored->verification['exists']);
		self::assertNotNull($stored->verifiedAt);
	}

	public function testVerifyApiFailureIsReportedAndNothingIsStored(): void
	{
		$tile = $this->tileFor('3 kwietnia - Dzień Kota');
		static::getContainer()->set(HttpClientInterface::class, new MockHttpClient(new MockResponse(
			json_encode(['error' => ['message' => 'Overloaded']]),
			['http_code' => 529]
		)));

		$this->postJson(sprintf('/admin/api/import/%d/verify', $tile), []);

		$this->assertResponseStatusCodeSame(502);
		self::assertSame('Overloaded', $this->json()['error']);
	}

	public function testActionsNeedTheCsrfToken(): void
	{
		$tile = $this->tileFor('3 kwietnia - Dzień Kota');

		$this->client->request('POST', sprintf('/admin/api/import/%d/remove', $tile), [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['_token' => 'nope']));

		$this->assertResponseStatusCodeSame(403);
		self::assertNotNull($this->entityManager()->getRepository(HolidayImportCandidate::class)->find($tile));
	}

	public function testShowReturnsNullOnceTheTileIsGone(): void
	{
		$this->client->request('GET', '/admin/api/import/999999');

		$this->assertResponseIsSuccessful();
		self::assertNull($this->json()['html']);
	}

	public function testNewHolidayFormTakesThePrefillFromATile(): void
	{
		$crawler = $this->client->request('GET', '/admin/holiday/fixed/new?name=Dzie%C5%84%20Kota&day=3&month=4&url=https%3A%2F%2Fexample.org%2Fkot');

		$this->assertResponseIsSuccessful();
		self::assertSame('Dzień Kota', $crawler->filter('#source-name')->text());
		self::assertSame('https://example.org/kot', $crawler->filter('#source-url')->attr('value'));
		self::assertSame('3', $crawler->filter('#meta-day')->attr('value'));
	}

	public function testNewHolidayFormIgnoresANonHttpUrl(): void
	{
		$crawler = $this->client->request('GET', '/admin/holiday/fixed/new?url=javascript%3Aalert(1)');

		$this->assertResponseIsSuccessful();
		self::assertSame('', $crawler->filter('#source-url')->attr('value'));
	}

	public function testAnonymousVisitorIsSentToTheLogin(): void
	{
		$this->client->getCookieJar()->clear();

		$this->client->request('GET', '/admin/import');

		$this->assertResponseRedirects('http://localhost/');
	}

	private function paste(string $text): void
	{
		$crawler = $this->client->request('GET', '/admin/import');
		$token = $crawler->filter('form.import-paste input[name="_token"]')->attr('value');
		$this->client->request('POST', '/admin/import', ['text' => $text, '_token' => $token]);
	}

	/**
	 * Pastes one line and returns the id of the tile it made.
	 */
	private function tileFor(string $line): int
	{
		$this->paste($line);
		$tile = $this->entityManager()->getRepository(HolidayImportCandidate::class)->findOneBy([], ['id' => 'DESC']);
		self::assertNotNull($tile);
		return $tile->id;
	}

	/**
	 * The token is session-bound, so it is read from a page this client rendered.
	 *
	 * @param array<string, mixed> $body
	 */
	private function postJson(string $url, array $body): void
	{
		$crawler = $this->client->request('GET', '/admin/import');
		$token = $crawler->filter('[data-import-page]')->attr('data-csrf-token');
		$this->client->request('POST', $url, [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode([...$body, '_token' => $token]));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function json(): array
	{
		return json_decode((string)$this->client->getResponse()->getContent(), true);
	}

	private function entityManager(): EntityManagerInterface
	{
		return static::getContainer()->get(EntityManagerInterface::class);
	}
}
