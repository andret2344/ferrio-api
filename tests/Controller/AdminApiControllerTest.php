<?php

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use App\Tests\Fixture\AdminUserFixture;
use App\Tests\Fixture\CountryFixture;
use App\Tests\Fixture\FixedHolidayFixture;
use App\Tests\Fixture\FixedHolidayMetadataFixture;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminApiControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private AbstractDatabaseTool $databaseTool;
	private int $metadataId;

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
		]);
		$this->metadataId = $fixtures->getReferenceRepository()
			->getReference(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class)->id;

		$this->client->loginUser($fixtures->getReferenceRepository()
			->getReference(AdminUserFixture::ADMIN, AdminUser::class));
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
	}

	public function testSaveStoresTheSourceUrlOfEachLanguage(): void
	{
		$this->save([
			'source' => ['name' => 'Pierwszy marca', 'description' => 'Opis', 'url' => ' https://pl.example.org/dzien '],
			'translations' => [
				'en' => ['name' => 'March First', 'description' => 'A test fixed holiday', 'url' => 'https://en.example.org/day', 'ai_generated' => true],
			],
		]);

		$this->assertResponseIsSuccessful();
		self::assertSame('https://pl.example.org/dzien', $this->storedUrl('pl'));
		self::assertSame('https://en.example.org/day', $this->storedUrl('en'));
	}

	public function testSaveClearsAnEmptiedSourceUrl(): void
	{
		$this->save([
			'source' => ['name' => 'Pierwszy marca', 'description' => 'Opis', 'url' => 'https://pl.example.org/dzien'],
			'translations' => [
				'en' => ['name' => 'March First', 'description' => 'A test fixed holiday', 'url' => 'https://en.example.org/day', 'ai_generated' => true],
			],
		]);
		$this->save([
			'source' => ['name' => 'Pierwszy marca', 'description' => 'Opis', 'url' => ''],
			'translations' => [
				'en' => ['name' => 'March First', 'description' => 'A test fixed holiday', 'url' => '', 'ai_generated' => true],
			],
		]);

		$this->assertResponseIsSuccessful();
		self::assertSame('', $this->storedUrl('en'));
	}

	public function testDetailPagePrefillsTheStoredUrls(): void
	{
		$this->save([
			'source' => ['name' => 'Pierwszy marca', 'description' => 'Opis', 'url' => 'https://pl.example.org/dzien'],
			'translations' => [
				'en' => ['name' => 'March First', 'description' => 'A test fixed holiday', 'url' => 'https://en.example.org/day', 'ai_generated' => true],
			],
		]);

		$crawler = $this->client->request('GET', sprintf('/admin/holiday/fixed/%d', $this->metadataId));

		$this->assertResponseIsSuccessful();
		self::assertSame('https://pl.example.org/dzien', $crawler->filter('#source-url')->attr('value'));
		$translations = json_decode($crawler->filter('#translations-data')->text(), true);
		self::assertSame('https://en.example.org/day', $translations['en']['url']);
	}

	/**
	 * The save CSRF token is session-bound, so it is read from the detail page the same client
	 * rendered rather than pulled from the container.
	 *
	 * @param array<string, mixed> $body
	 */
	private function save(array $body): void
	{
		$url = sprintf('/admin/holiday/fixed/%d', $this->metadataId);
		$crawler = $this->client->request('GET', $url);
		$token = $crawler->filter("[data-page='admin-holiday-detail']")->attr('data-csrf-token');

		$this->client->request('POST', sprintf('/admin/api/holiday/fixed/%d', $this->metadataId), [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode([
			...$body,
			'metadata' => ['country' => 'GB', 'mature' => false, 'tags' => [], 'day' => 1, 'month' => 3],
			'_token' => $token,
		]));
	}

	private function storedUrl(string $language): ?string
	{
		$entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$entityManager->clear();
		$holiday = $entityManager->getRepository(FixedHoliday::class)
			->findOneBy(['metadata' => $this->metadataId, 'language' => $language]);
		self::assertNotNull($holiday);
		return $holiday->url;
	}
}
