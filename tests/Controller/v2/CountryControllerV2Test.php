<?php

namespace App\Tests\Controller\v2;

use App\Entity\Country;
use App\Tests\Fixture\CountryFixture;
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

class CountryControllerV2Test extends WebTestCase
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
		$this->client->followRedirects();

		$this->databaseTool = static::getContainer()
			->get(DatabaseToolCollection::class)
			->get();

		$this->fixtures = $this->databaseTool->loadFixtures([
			CountryFixture::class
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
	 * @throws TransportExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws RedirectionExceptionInterface
	 * @throws ServerExceptionInterface
	 * @throws JsonException
	 */
	public function testGet(): void
	{
		/** @var Country $countryGb */
		$countryGb = $this->getFixture('country-gb', Country::class);
		/** @var Country $countryPl */
		$countryPl = $this->getFixture('country-pl', Country::class);

		$this->request(
			'GET',
			'/v2/countries'
		);

		$this->assertResponseIsSuccessful();
		$response = $this->client->getResponse()
			->getContent();

		$expected = json_encode([
			$countryGb,
			$countryPl
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
	public function testGetCodes(): void
	{
		/** @var Country $countryGb */
		$countryGb = $this->getFixture('country-gb', Country::class);
		/** @var Country $countryPl */
		$countryPl = $this->getFixture('country-pl', Country::class);

		$this->request(
			'GET',
			'/v2/countries',
			['format' => 'code']
		);

		$this->assertResponseIsSuccessful();
		$response = $this->client->getResponse()
			->getContent();

		$expected = json_encode([
			$countryGb->isoCode,
			$countryPl->isoCode
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
	public function testGetNames(): void
	{
		/** @var Country $countryGb */
		$countryGb = $this->getFixture('country-gb', Country::class);
		/** @var Country $countryPl */
		$countryPl = $this->getFixture('country-pl', Country::class);

		$this->request(
			'GET',
			'/v2/countries',
			['format' => 'name']
		);

		$this->assertResponseIsSuccessful();
		$response = $this->client->getResponse()
			->getContent();

		$expected = json_encode([
			$countryGb->englishName,
			$countryPl->englishName
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
	public function testGetAll(): void
	{
		/** @var Country $countryGb */
		$countryGb = $this->getFixture('country-gb', Country::class);
		/** @var Country $countryPl */
		$countryPl = $this->getFixture('country-pl', Country::class);

		$this->request(
			'GET',
			'/v2/countries',
			['format' => 'all']
		);

		$this->assertResponseIsSuccessful();
		$response = $this->client->getResponse()
			->getContent();

		$expected = json_encode([
			$countryGb,
			$countryPl
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
	public function testGetInvalidFormat(): void
	{
		$this->request(
			'GET',
			'/v2/countries',
			['format' => 'invalid']
		);

		$this->assertResponseStatusCodeSame(400);
		$response = $this->client->getResponse()
			->getContent();

		$expected = json_encode(['error' => 'Invalid format, use `code`, `name` or `all`, or skip format'], JSON_THROW_ON_ERROR);

		$this->assertJsonStringEqualsJsonString($expected, $response);
	}
}
