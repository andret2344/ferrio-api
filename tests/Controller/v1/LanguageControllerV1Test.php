<?php

namespace App\Tests\Controller\v1;

use App\Entity\Language;
use App\Tests\Fixture\LanguageFixture;
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
use function count;

class LanguageControllerV1Test extends WebTestCase
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
			LanguageFixture::class,
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
	public function testGetAll(): void
	{
		/** @var Language $langEn */
		$langEn = $this->getFixture('language-en', Language::class);
		/** @var Language $langPl */
		$langPl = $this->getFixture('language-pl', Language::class);

		$this->request('GET', '/v1/language/');

		$this->assertResponseIsSuccessful();

		$payload = json_decode($this->client->getResponse()
			->getContent(), true, 512, JSON_THROW_ON_ERROR);

		$this->assertIsArray($payload);
		$this->assertGreaterThanOrEqual(2, count($payload));

		$codes = array_map(fn(array $row) => $row['code'] ?? null, $payload);
		$this->assertContains($langEn->code, $codes);
		$this->assertContains($langPl->code, $codes);
	}

	/**
	 * @throws TransportExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws RedirectionExceptionInterface
	 * @throws ServerExceptionInterface
	 * @throws JsonException
	 */
	public function testGetOne(): void
	{
		/** @var Language $langPl */
		$langPl = $this->getFixture('language-pl', Language::class);

		$this->request('GET', "/v1/language/$langPl->code");

		$this->assertResponseIsSuccessful();

		$payload = json_decode($this->client->getResponse()
			->getContent(), true, 512, JSON_THROW_ON_ERROR);

		$this->assertIsArray($payload);
		$this->assertSame($langPl->code, $payload['code'] ?? null);
		$this->assertSame($langPl->name, $payload['name']);
	}

	/**
	 * @throws ClientExceptionInterface
	 * @throws JsonException
	 */
	public function testGetOneNotFound(): void
	{
		$this->request('GET', '/v1/language/xx');

		$this->assertResponseStatusCodeSame(404);
	}
}
