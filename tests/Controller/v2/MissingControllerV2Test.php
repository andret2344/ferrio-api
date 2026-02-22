<?php

namespace App\Tests\Controller\v2;

use App\Entity\Ban;
use App\Entity\FixedHolidaySuggestion;
use App\Entity\FloatingHolidaySuggestion;
use App\Tests\Fixture\BanFixture;
use App\Tests\Fixture\FixedHolidaySuggestionFixture;
use App\Tests\Fixture\FloatingHolidaySuggestionFixture;
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

class MissingControllerV2Test extends WebTestCase
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
			FixedHolidaySuggestionFixture::class,
			FloatingHolidaySuggestionFixture::class,
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
	public function testPostFixedMissing(): void
	{
		$this->request('POST', '/v2/missing/fixed', [], [
			'user_id' => 'user-id',
			'day' => 1,
			'month' => 1,
			'name' => 'Test name',
			'description' => 'Test description',
			'country' => 'GB'
		]);

		$this->assertResponseStatusCodeSame(201);

		$repo = $this->em->getRepository(FixedHolidaySuggestion::class);
		/** @var FixedHolidaySuggestion $entity */
		$entity = $repo->findOneBy(['userId' => 'user-id']);

		$this->assertNotNull($entity, 'Entity not stored in the DB');
		$this->assertSame('user-id', $entity->userId);
		$this->assertSame(1, $entity->day);
		$this->assertSame(1, $entity->month);
		$this->assertSame('Test name', $entity->name);
		$this->assertSame('Test description', $entity->description);
		$this->assertSame('GB', $entity->country->isoCode);
	}

	/**
	 * @throws TransportExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws RedirectionExceptionInterface
	 * @throws ServerExceptionInterface
	 * @throws JsonException
	 */
	public function testGetNonEmptyFixedMissingResponse(): void
	{
		/** @var FixedHolidaySuggestion $suggestion */
		$suggestion = $this->getFixture('fixed-holiday-suggestion', FixedHolidaySuggestion::class);

		$this->request('GET', '/v2/missing/user-id/fixed');

		$this->assertResponseIsSuccessful();
		$response = $this->client->getResponse()
			->getContent();
		$expected = json_encode([
			[
				'id' => $suggestion->id,
				'day' => 1,
				'month' => 1,
				'name' => 'Test name',
				'description' => 'Test description',
				'datetime' => $suggestion->datetime->format('Y-m-d H:i:s'),
				'country' => 'GB',
				'holiday_id' => null,
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
	public function testPostFixedErrorBannedUser(): void
	{
		/** @var Ban $ban */
		$ban = $this->getFixture('ban', Ban::class);

		$this->request('POST', '/v2/missing/fixed', [], [
			'user_id' => 'user-id-banned',
			'day' => 1,
			'month' => 1,
			'name' => 'Test name',
			'description' => 'Test description',
			'country' => 'GB'
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
	public function testPostFloatingMissing(): void
	{
		$this->request('POST', '/v2/missing/floating', [], [
			'user_id' => 'user-id',
			'date' => '01.01',
			'name' => 'Test name',
			'description' => 'Test description',
			'country' => 'GB'
		]);

		$this->assertResponseStatusCodeSame(201);

		$repo = $this->em->getRepository(FloatingHolidaySuggestion::class);
		/** @var FloatingHolidaySuggestion $entity */
		$entity = $repo->findOneBy(['userId' => 'user-id']);

		$this->assertNotNull($entity, 'Entity not stored in the DB');
		$this->assertSame('user-id', $entity->userId);
		$this->assertSame('01.01', $entity->date);
		$this->assertSame('Test name', $entity->name);
		$this->assertSame('Test description', $entity->description);
		$this->assertSame('GB', $entity->country->isoCode);
	}

	/**
	 * @throws TransportExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws RedirectionExceptionInterface
	 * @throws ServerExceptionInterface
	 * @throws JsonException
	 */
	public function testGetNonEmptyFloatingMissingResponse(): void
	{
		/** @var FloatingHolidaySuggestion $suggestion */
		$suggestion = $this->getFixture('floating-holiday-suggestion', FloatingHolidaySuggestion::class);

		$this->request('GET', '/v2/missing/user-id/floating');

		$this->assertResponseIsSuccessful();
		$response = $this->client->getResponse()
			->getContent();
		$expected = json_encode([
			[
				'id' => $suggestion->id,
				'date' => '01.01',
				'name' => 'Test name',
				'description' => 'Test description',
				'datetime' => $suggestion->datetime->format('Y-m-d H:i:s'),
				'holiday_id' => null,
				'country' => 'GB',
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
	public function testPostFloatingErrorBannedUser(): void
	{
		/** @var Ban $ban */
		$ban = $this->getFixture('ban', Ban::class);

		$this->request('POST', '/v2/missing/floating', [], [
			'user_id' => 'user-id-banned',
			'date' => '01.01',
			'name' => 'Test name',
			'description' => 'Test description',
			'country' => 'GB'
		]);

		$this->assertResponseStatusCodeSame(403);
		$response = $this->client->getResponse()
			->getContent();

		$actual = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame(['reason' => $ban->reason], $actual);
	}

	public function testPostFixedMissingRequiredFields(): void
	{
		$this->request('POST', '/v2/missing/fixed', [], [
			'user_id' => 'user-id',
		]);

		$this->assertResponseStatusCodeSame(422);
	}

	public function testPostFixedInvalidJson(): void
	{
		$this->client->request('POST', '/v2/missing/fixed', [], [], [
			'CONTENT_TYPE' => 'application/json',
			'HTTP_ACCEPT' => 'application/json',
		], 'not-json');

		$this->assertResponseStatusCodeSame(400);
	}

	public function testPostFloatingMissingRequiredFields(): void
	{
		$this->request('POST', '/v2/missing/floating', [], [
			'user_id' => 'user-id',
		]);

		$this->assertResponseStatusCodeSame(422);
	}

	public function testPostFloatingInvalidJson(): void
	{
		$this->client->request('POST', '/v2/missing/floating', [], [], [
			'CONTENT_TYPE' => 'application/json',
			'HTTP_ACCEPT' => 'application/json',
		], 'not-json');

		$this->assertResponseStatusCodeSame(400);
	}
}
