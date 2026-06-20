<?php

namespace App\Tests\Controller\v3;

use App\Entity\Ban;
use App\Entity\FixedHolidayError;
use App\Entity\FixedHolidayMetadata;
use App\Entity\FixedHolidaySuggestion;
use App\Entity\FloatingHolidayError;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\FloatingHolidaySuggestion;
use App\Entity\Language;
use App\Tests\Fixture\BanFixture;
use App\Tests\Fixture\CountryFixture;
use App\Tests\Fixture\FixedHolidayErrorFixture;
use App\Tests\Fixture\FixedHolidayMetadataFixture;
use App\Tests\Fixture\FloatingHolidayErrorFixture;
use App\Tests\Trait\TestUtilTrait;
use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class UserControllerV3Test extends WebTestCase
{
	use TestUtilTrait;

	private EntityManagerInterface $em;
	private AbstractDatabaseTool $databaseTool;
	private AbstractExecutor $fixtures;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->client = static::createClient();

		$this->databaseTool = static::getContainer()
			->get(DatabaseToolCollection::class)
			->get();

		$this->fixtures = $this->databaseTool->loadFixtures([
			FixedHolidayErrorFixture::class,
			FloatingHolidayErrorFixture::class,
			BanFixture::class,
			CountryFixture::class,
		]);

		$this->em = static::getContainer()
			->get(EntityManagerInterface::class);
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
	}

	/**
	 * @throws ClientExceptionInterface
	 * @throws RedirectionExceptionInterface
	 * @throws ServerExceptionInterface
	 * @throws JsonException
	 */
	public function testPostFixedReport(): void
	{
		/** @var Language $language */
		$language = $this->getFixture('language-en', Language::class);
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'fixed'],
			[
				'language' => $language->code,
				'metadata' => $metadata->id,
				'report_type' => 'OTHER',
				'description' => 'Test description',
			],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseStatusCodeSame(201);

		$repo = $this->em->getRepository(FixedHolidayError::class);
		$entity = $repo->findOneBy(['userId' => 'user-id']);

		$this->assertNotNull($entity, 'Entity not stored in the DB');
		$this->assertSame('user-id', $entity->userId);
	}

	/**
	 * @throws TransportExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws RedirectionExceptionInterface
	 * @throws ServerExceptionInterface
	 * @throws JsonException
	 */
	public function testGetNonEmptyFixedReportsResponse(): void
	{
		/** @var Language $language */
		$language = $this->getFixture('language-en', Language::class);
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);
		/** @var FixedHolidayError $error */
		$error = $this->getFixture('fixed-holiday-error', FixedHolidayError::class);

		$this->request(
			'GET',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'fixed'],
			[],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseIsSuccessful();
		$response = $this->client->getResponse()
			->getContent();

		$expected = json_encode([
			[
				'id' => $error->id,
				'description' => 'Test desc',
				'language_code' => $language->code,
				'metadata_id' => $metadata->id,
				'report_type' => 'OTHER',
				'datetime' => $error->datetime->format('Y-m-d H:i:s'),
				'report_state' => 'REPORTED',
				'comment' => null,
				'user_id' => 'user-id',
				'platform' => 'unknown',
				'real_device' => null,
				'device_country' => null
			]
		], JSON_THROW_ON_ERROR);

		$this->assertJsonStringEqualsJsonString($expected, $response);
	}

	/**
	 * @throws TransportExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws RedirectionExceptionInterface
	 * @throws ServerExceptionInterface
	 * @throws JsonException
	 */
	public function testPostFixedErrorBannedUser(): void
	{
		/** @var Ban $ban */
		$ban = $this->getFixture('ban', Ban::class);
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'fixed'],
			[
				'language' => 'en',
				'metadata' => $metadata->id,
				'report_type' => 'OTHER',
				'description' => 'Test description',
			],
			['Authorization' => 'Bearer banned-token']
		);

		$this->assertResponseStatusCodeSame(403);
		$response = $this->client->getResponse()
			->getContent();

		$actual = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame(['reason' => $ban->reason], $actual);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFloatingReport(): void
	{
		/** @var Language $language */
		$language = $this->getFixture('language-en', Language::class);
		/** @var FloatingHolidayMetadata $metadata */
		$metadata = $this->getFixture('floating-holiday-metadata', FloatingHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'floating'],
			[
				'language' => $language->code,
				'metadata' => $metadata->id,
				'report_type' => 'OTHER',
				'description' => 'Test description',
			],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseStatusCodeSame(201);

		$repo = $this->em->getRepository(FloatingHolidayError::class);
		$entity = $repo->findOneBy(['userId' => 'user-id']);

		$this->assertNotNull($entity, 'Entity not stored in the DB');
		$this->assertSame('user-id', $entity->userId);
	}

	/**
	 * @throws TransportExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws RedirectionExceptionInterface
	 * @throws ServerExceptionInterface
	 * @throws JsonException
	 */
	public function testGetNonEmptyFloatingReportsResponse(): void
	{
		/** @var Language $language */
		$language = $this->getFixture('language-en', Language::class);
		/** @var FloatingHolidayMetadata $metadata */
		$metadata = $this->getFixture('floating-holiday-metadata', FloatingHolidayMetadata::class);
		/** @var FloatingHolidayError $error */
		$error = $this->getFixture('floating-holiday-error', FloatingHolidayError::class);

		$this->request(
			'GET',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'floating'],
			[],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseIsSuccessful();
		$response = $this->client->getResponse()
			->getContent();

		$expected = json_encode([
			[
				'id' => $error->id,
				'description' => 'Test desc',
				'language_code' => $language->code,
				'metadata_id' => $metadata->id,
				'report_type' => 'OTHER',
				'datetime' => $error->datetime->format('Y-m-d H:i:s'),
				'report_state' => 'REPORTED',
				'comment' => 'Reviewed by admin',
				'user_id' => 'user-id',
				'platform' => 'unknown',
				'real_device' => null,
				'device_country' => null
			]
		], JSON_THROW_ON_ERROR);

		$this->assertJsonStringEqualsJsonString($expected, $response);
	}

	/**
	 * @throws TransportExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws RedirectionExceptionInterface
	 * @throws ServerExceptionInterface
	 * @throws JsonException
	 */
	public function testPostFloatingErrorBannedUser(): void
	{
		/** @var Ban $ban */
		$ban = $this->getFixture('ban', Ban::class);
		/** @var FloatingHolidayMetadata $metadata */
		$metadata = $this->getFixture('floating-holiday-metadata', FloatingHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'floating'],
			[
				'language' => 'en',
				'metadata' => $metadata->id,
				'report_type' => 'OTHER',
				'description' => 'Test description',
			],
			['Authorization' => 'Bearer banned-token']
		);

		$this->assertResponseStatusCodeSame(403);
		$response = $this->client->getResponse()
			->getContent();

		$actual = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame(['reason' => $ban->reason], $actual);
	}

	/**
	 * @throws JsonException
	 */
	public function testMissingAuthorizationHeader(): void
	{
		$this->request(
			'GET',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'fixed']
		);

		$this->assertResponseStatusCodeSame(401);
	}

	/**
	 * @throws JsonException
	 */
	public function testAnonymousTokenRejected(): void
	{
		$this->request(
			'GET',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'fixed'],
			[],
			['Authorization' => 'Bearer anonymous-token']
		);

		$this->assertResponseStatusCodeSame(401);
	}

	/**
	 * @throws JsonException
	 */
	public function testAnonymousTokenRejectedOnPost(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'fixed'],
			[
				'language' => 'en',
				'metadata' => $metadata->id,
				'report_type' => 'OTHER',
				'description' => 'Test description',
			],
			['Authorization' => 'Bearer anonymous-token']
		);

		$this->assertResponseStatusCodeSame(401);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportWithPlatformAndRealDevice(): void
	{
		/** @var Language $language */
		$language = $this->getFixture('language-en', Language::class);
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'fixed'],
			[
				'language' => $language->code,
				'metadata' => $metadata->id,
				'report_type' => 'OTHER',
				'description' => 'Test description',
				'platform' => 'android',
				'real_device' => 'Pixel 8 Pro',
				'device_country' => 'PL',
			],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseStatusCodeSame(201);

		$repo = $this->em->getRepository(FixedHolidayError::class);
		$entity = $repo->findOneBy(['userId' => 'user-id']);

		$this->assertNotNull($entity);
		$this->assertNotNull($entity->platform);
		$this->assertSame('android', $entity->platform->value);
		$this->assertSame('Pixel 8 Pro', $entity->realDevice);
		$this->assertNotNull($entity->deviceCountry);
		$this->assertSame('PL', $entity->deviceCountry->isoCode);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFloatingReportWithPlatformAndRealDevice(): void
	{
		/** @var Language $language */
		$language = $this->getFixture('language-en', Language::class);
		/** @var FloatingHolidayMetadata $metadata */
		$metadata = $this->getFixture('floating-holiday-metadata', FloatingHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'floating'],
			[
				'language' => $language->code,
				'metadata' => $metadata->id,
				'report_type' => 'OTHER',
				'description' => 'Test description',
				'platform' => 'web',
				'real_device' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
			],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseStatusCodeSame(201);

		$repo = $this->em->getRepository(FloatingHolidayError::class);
		$entity = $repo->findOneBy(['userId' => 'user-id']);

		$this->assertNotNull($entity);
		$this->assertNotNull($entity->platform);
		$this->assertSame('web', $entity->platform->value);
		$this->assertSame('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', $entity->realDevice);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportUnknownPlatformStoredAsUnknown(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'fixed'],
			[
				'language' => 'en',
				'metadata' => $metadata->id,
				'report_type' => 'OTHER',
				'description' => 'Test description',
				'platform' => 'windows',
			],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id']);

		$this->assertNotNull($entity);
		$this->assertSame('unknown', $entity->platform->value);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportLowercaseDeviceCountryNormalized(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'fixed'],
			[
				'language' => 'en',
				'metadata' => $metadata->id,
				'report_type' => 'OTHER',
				'description' => 'Test description',
				'device_country' => 'gb',
			],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id']);

		$this->assertNotNull($entity);
		$this->assertNotNull($entity->deviceCountry);
		$this->assertSame('GB', $entity->deviceCountry->isoCode);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportTooLongRealDeviceRejected(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'fixed'],
			[
				'language' => 'en',
				'metadata' => $metadata->id,
				'report_type' => 'OTHER',
				'description' => 'Test description',
				'real_device' => str_repeat('x', 2001),
			],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseStatusCodeSame(422);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFloatingReportWebPlatformFallsBackToUserAgent(): void
	{
		/** @var FloatingHolidayMetadata $metadata */
		$metadata = $this->getFixture('floating-holiday-metadata', FloatingHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'floating'],
			[
				'language' => 'en',
				'metadata' => $metadata->id,
				'report_type' => 'OTHER',
				'description' => 'Test description',
				'platform' => 'web',
			],
			[
				'Authorization' => 'Bearer test-token',
				'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2) Safari/605.1.15',
			]
		);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FloatingHolidayError::class)
			->findOneBy(['userId' => 'user-id']);

		$this->assertNotNull($entity);
		$this->assertSame('Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2) Safari/605.1.15', $entity->realDevice);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportApiPlatformIgnoresUserAgent(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'fixed'],
			[
				'language' => 'en',
				'metadata' => $metadata->id,
				'report_type' => 'OTHER',
				'description' => 'Test description',
				'platform' => 'api',
			],
			[
				'Authorization' => 'Bearer test-token',
				'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/123.0',
			]
		);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id']);

		$this->assertNotNull($entity);
		$this->assertNull($entity->realDevice);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportInvalidReportType(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'fixed'],
			[
				'language' => 'en',
				'metadata' => $metadata->id,
				'report_type' => 'NOT_A_REAL_TYPE',
				'description' => 'Test description',
			],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseStatusCodeSame(422);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostUnknownReportTypeReturns400(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'nonsense', 'holidayType' => 'fixed'],
			[
				'language' => 'en',
				'metadata' => $metadata->id,
				'report_type' => 'OTHER',
				'description' => 'Test description',
			],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseStatusCodeSame(400);
	}

	/**
	 * @throws JsonException
	 */
	public function testInvalidToken(): void
	{
		$this->request(
			'GET',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'fixed'],
			[],
			['Authorization' => 'Bearer invalid-token']
		);

		$this->assertResponseStatusCodeSame(401);
		$response = $this->client->getResponse()
			->getContent();

		$actual = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame(['error' => 'Invalid token'], $actual);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedSuggestionWithDeviceMeta(): void
	{
		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'suggestion', 'holidayType' => 'fixed'],
			[
				'name' => 'New Holiday',
				'description' => 'Test description',
				'day' => 5,
				'month' => 5,
				'country' => 'GB',
				'platform' => 'ios',
				'real_device' => 'iPhone 15',
				'device_country' => 'pl',
			],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidaySuggestion::class)
			->findOneBy(['userId' => 'user-id']);

		$this->assertNotNull($entity);
		$this->assertSame('ios', $entity->platform->value);
		$this->assertSame('iPhone 15', $entity->realDevice);
		$this->assertSame('GB', $entity->country->isoCode);
		$this->assertSame('PL', $entity->deviceCountry->isoCode);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFloatingSuggestionWebPlatformFallsBackToUserAgent(): void
	{
		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'suggestion', 'holidayType' => 'floating'],
			[
				'name' => 'New Floating Holiday',
				'description' => 'Test description',
				'date' => '12.06',
				'platform' => 'web',
			],
			[
				'Authorization' => 'Bearer test-token',
				'User-Agent' => 'Mozilla/5.0 v3-suggestion-ua',
			]
		);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FloatingHolidaySuggestion::class)
			->findOneBy(['userId' => 'user-id']);

		$this->assertNotNull($entity);
		$this->assertSame('Mozilla/5.0 v3-suggestion-ua', $entity->realDevice);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedSuggestionUppercasePlatformNormalized(): void
	{
		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'suggestion', 'holidayType' => 'fixed'],
			[
				'name' => 'New Holiday',
				'description' => 'desc',
				'day' => 5,
				'month' => 5,
				'platform' => 'IOS',
			],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidaySuggestion::class)
			->findOneBy(['userId' => 'user-id']);

		$this->assertNotNull($entity);
		$this->assertSame('ios', $entity->platform->value);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportWhitespaceOnlyRealDeviceFallsBackToUserAgent(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request(
			'POST',
			'/v3/users/reports',
			['reportType' => 'error', 'holidayType' => 'fixed'],
			[
				'language' => 'en',
				'metadata' => $metadata->id,
				'report_type' => 'OTHER',
				'description' => 'Test description',
				'platform' => 'web',
				'real_device' => '   ',
			],
			[
				'Authorization' => 'Bearer test-token',
				'User-Agent' => 'UA-from-fallback/1.0',
			]
		);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id']);

		$this->assertNotNull($entity);
		$this->assertSame('UA-from-fallback/1.0', $entity->realDevice);
	}
}
