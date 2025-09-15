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

class ReportControllerV2Test extends WebTestCase {
	use TestUtilTrait;

	private EntityManagerInterface $em;
	private AbstractDatabaseTool $databaseTool;
	private AbstractExecutor $fixtures;

	#[Override]
	protected function setUp(): void {
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
	protected function tearDown(): void {
		parent::tearDown();
		unset($this->databaseTool);
	}

	/**
	 * @throws TransportExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws RedirectionExceptionInterface
	 * @throws ServerExceptionInterface
	 * @throws JsonException
	 */
	public function testPostFixedReport(): void {
		/** @var Language $language */
		$language = $this->getFixture('language-en', Language::class);
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture('fixed-holiday-metadata', FixedHolidayMetadata::class);

		$this->request('POST', '/v2/report/fixed', [], [
			'user_id' => 'user-id',
			'language' => $language->code,
			'metadata' => $metadata->id,
			'report_type' => 'OTHER',
			'description' => 'Test description',
		]);

		$this->assertResponseStatusCodeSame(204);

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
	public function testGetNonEmptyFixedReportsResponse(): void {
		/** @var Language $language */
		$language = $this->getFixture('language-en', Language::class);
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture('fixed-holiday-metadata', FixedHolidayMetadata::class);
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
				'user_id' => 'user-id'
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
	public function testPostFixedErrorBannedUser(): void {
		/** @var Ban $ban */
		$ban = $this->getFixture('ban', Ban::class);
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->getFixture('fixed-holiday-metadata', FixedHolidayMetadata::class);

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
	public function testPostFloatingReport(): void {
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

		$this->assertResponseStatusCodeSame(204);

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
	public function testGetNonEmptyFloatingReportsResponse(): void {
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
				'user_id' => 'user-id'
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
	public function testPostFloatingErrorBannedUser(): void {
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
}
