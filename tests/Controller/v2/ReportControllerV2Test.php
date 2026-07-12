<?php

namespace App\Tests\Controller\v2;

use App\Entity\Ban;
use App\Entity\FixedHolidayError;
use App\Entity\FixedHolidayMetadata;
use App\Entity\FloatingHolidayError;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\Language;
use App\Tests\Fixture\BanFixture;
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

class ReportControllerV2Test extends WebTestCase
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
			BanFixture::class
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

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => $language->code,
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
		]);

		$this->assertResponseStatusCodeSame(201);

		$repo = $this->em->getRepository(FixedHolidayError::class);
		$entity = $repo->findOneBy(['userId' => 'user-id'], ['id' => 'DESC']);

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

		$this->request('GET', '/v2/report/user-id/fixed');

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
				'device' => [
					'platform' => 'unknown',
					'model' => null,
					'country' => null,
					'os_version' => null,
					'app_version' => null,
					'app_build' => null,
				]
			]
		]);

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

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id-banned',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
		]);

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

		$this->request('POST', '/v2/report/floating', [], [
			'user_id' => 'user-id',
			'language' => $language->code,
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
		]);

		$this->assertResponseStatusCodeSame(201);

		$repo = $this->em->getRepository(FloatingHolidayError::class);
		$entity = $repo->findOneBy(['userId' => 'user-id'], ['id' => 'DESC']);

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

		$this->request('GET', '/v2/report/user-id/floating');

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
				'device' => [
					'platform' => 'unknown',
					'model' => null,
					'country' => null,
					'os_version' => null,
					'app_version' => null,
					'app_build' => null,
				]
			]
		]);

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

		$this->request('POST', '/v2/report/floating', [], [
			'user_id' => 'user-id-banned',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
		]);

		$this->assertResponseStatusCodeSame(403);
		$response = $this->client->getResponse()
			->getContent();

		$actual = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame(['reason' => $ban->reason], $actual);
	}

	public function testPostFixedReportMissingRequiredFields(): void
	{
		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
		]);

		$this->assertResponseStatusCodeSame(422);
	}

	public function testPostFixedReportInvalidJson(): void
	{
		$this->client->request('POST', '/v2/report/fixed', [], [], [
			'CONTENT_TYPE' => 'application/json',
			'HTTP_ACCEPT' => 'application/json',
		], 'not-json');

		$this->assertResponseStatusCodeSame(400);
	}

	public function testPostFloatingReportMissingRequiredFields(): void
	{
		$this->request('POST', '/v2/report/floating', [], [
			'user_id' => 'user-id',
		]);

		$this->assertResponseStatusCodeSame(422);
	}

	public function testPostFixedReportWithPlatformAndRealDevice(): void
	{
		/** @var Language $language */
		$language = $this->getFixture('language-en', Language::class);
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => $language->code,
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
			'device' => [
				'platform' => 'ios',
				'model' => 'iPhone 15 Pro',
				'country' => 'GB',
			],
		]);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id'], ['id' => 'DESC']);

		$this->assertNotNull($entity);
		$this->assertNotNull($entity->platform);
		$this->assertSame('ios', $entity->platform->value);
		$this->assertSame('iPhone 15 Pro', $entity->realDevice);
		$this->assertSame('GB', $entity->deviceCountry);
	}

	public function testPostFixedReportUnknownPlatformStoredAsUnknown(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
			'device' => [
				'platform' => 'symbian',
			],
		]);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id'], ['id' => 'DESC']);

		$this->assertNotNull($entity);
		$this->assertSame('unknown', $entity->platform->value);
	}

	public function testPostFloatingReportInvalidReportType(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/floating', [], [
			'user_id' => 'user-id',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'INVALID_TYPE',
			'description' => 'Test description',
		]);

		$this->assertResponseStatusCodeSame(422);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportLowercaseDeviceCountryNormalized(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
			'device' => [
				'country' => 'pl',
			],
		]);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id'], ['id' => 'DESC']);

		$this->assertNotNull($entity);
		$this->assertSame('PL', $entity->deviceCountry);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportUppercasePlatformNormalized(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
			'device' => [
				'platform' => 'ANDROID',
			],
		]);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id'], ['id' => 'DESC']);

		$this->assertNotNull($entity);
		$this->assertSame('android', $entity->platform->value);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportTooLongRealDeviceRejected(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
			'device' => [
				'model' => str_repeat('x', 2001),
			],
		]);

		$this->assertResponseStatusCodeSame(422);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportWebPlatformFallsBackToUserAgent(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
			'device' => [
				'platform' => 'web',
			],
		], ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/123.0']);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id'], ['id' => 'DESC']);

		$this->assertNotNull($entity);
		$this->assertSame('Mozilla/5.0 (X11; Linux x86_64) Firefox/123.0', $entity->realDevice);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportNonWebPlatformDoesNotFallBackToUserAgent(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
			'device' => [
				'platform' => 'android',
			],
		], ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/123.0']);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id'], ['id' => 'DESC']);

		$this->assertNotNull($entity);
		$this->assertNull($entity->realDevice);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportWebPlatformPrefersExplicitRealDevice(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
			'device' => [
				'platform' => 'web',
				'model' => 'Custom UA Override',
			],
		], ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/123.0']);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id'], ['id' => 'DESC']);

		$this->assertNotNull($entity);
		$this->assertSame('Custom UA Override', $entity->realDevice);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportTrimsRealDevice(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
			'device' => [
				'platform' => 'android',
				'model' => '  Pixel 8 Pro  ',
			],
		]);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id'], ['id' => 'DESC']);

		$this->assertNotNull($entity);
		$this->assertSame('Pixel 8 Pro', $entity->realDevice);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportWhitespaceOnlyRealDeviceFallsBackToUserAgent(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
			'device' => [
				'platform' => 'web',
				'model' => '   ',
			],
		], ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/123.0']);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id'], ['id' => 'DESC']);

		$this->assertNotNull($entity);
		$this->assertSame('Mozilla/5.0 (X11; Linux x86_64) Firefox/123.0', $entity->realDevice);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportKeepsDeviceCountryAbsentFromCountryTable(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
			'device' => [
				'country' => 'ZZ',
			],
		]);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id'], ['id' => 'DESC']);

		$this->assertNotNull($entity);
		$this->assertSame('ZZ', $entity->deviceCountry);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportPersistsOsAndAppVersion(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
			'device' => [
				'platform' => 'android',
				'os_version' => '14',
				'app_version' => '3.2.0',
			],
		]);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id'], ['id' => 'DESC']);

		$this->assertNotNull($entity);
		$this->assertSame('14', $entity->osVersion);
		$this->assertSame('3.2.0', $entity->appVersion);
	}

	/**
	 * @throws JsonException
	 */
	public function testPostFixedReportRejectsTooLongAppVersion(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
			'device' => [
				'app_version' => str_repeat('9', 65),
			],
		]);

		$this->assertResponseStatusCodeSame(422);
	}

	/**
	 * Backward compatibility: a legacy client that still sends the device fields flat at the top
	 * level (pre-`device` object) must not be rejected — the unknown keys are ignored and the
	 * report is stored with default device metadata.
	 *
	 * @throws JsonException
	 */
	public function testPostFixedReportIgnoresLegacyFlatDeviceFields(): void
	{
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => 'en',
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
			'platform' => 'android',
			'real_device' => 'Pixel 8 Pro',
			'device_country' => 'PL',
			'os_version' => '14',
			'app_version' => '3.2.0',
		]);

		$this->assertResponseStatusCodeSame(201);

		$entity = $this->em->getRepository(FixedHolidayError::class)
			->findOneBy(['userId' => 'user-id'], ['id' => 'DESC']);

		$this->assertNotNull($entity);
		$this->assertSame('unknown', $entity->platform->value);
		$this->assertNull($entity->realDevice);
		$this->assertNull($entity->deviceCountry);
		$this->assertNull($entity->osVersion);
		$this->assertNull($entity->appVersion);
	}
}
