<?php

namespace App\Tests\Controller\v2;

use App\Tests\Fixture\FixedHolidayFixture;
use App\Tests\Fixture\FloatingHolidayFixture;
use App\Tests\Trait\TestUtilTrait;
use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HolidayControllerV2Test extends WebTestCase
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
			FixedHolidayFixture::class,
			FloatingHolidayFixture::class,
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
	 * @throws JsonException
	 */
	public function testGetAllReturnsFixedAndFloating(): void
	{
		$this->request('GET', '/v2/holiday/en');

		$this->assertResponseIsSuccessful();
		$response = json_decode(
			$this->client->getResponse()->getContent(),
			true,
			512,
			JSON_THROW_ON_ERROR,
		);

		$this->assertArrayHasKey('fixed', $response);
		$this->assertArrayHasKey('floating', $response);
		$this->assertIsArray($response['fixed']);
		$this->assertIsArray($response['floating']);

		// Fixed: only March 1 should appear (March 14 is mature-content, filtered out)
		$this->assertCount(1, $response['fixed']);
		$this->assertSame('0301', $response['fixed'][0]['id']);
		$this->assertSame(1, $response['fixed'][0]['day']);
		$this->assertSame(3, $response['fixed'][0]['month']);
		$this->assertCount(1, $response['fixed'][0]['holidays']);
		$this->assertSame('March First', $response['fixed'][0]['holidays'][0]['name']);

		// Floating: the test fixture's hardcoded floating holiday
		$this->assertCount(1, $response['floating']);
		$this->assertSame('Floating Test Day', $response['floating'][0]['name']);
	}

	/**
	 * @throws JsonException
	 */
	public function testGetHolidayDayReturnsMatchingHolidays(): void
	{
		$this->request('GET', '/v2/holiday/en/day/3/1');

		$this->assertResponseIsSuccessful();
		$response = json_decode(
			$this->client->getResponse()->getContent(),
			true,
			512,
			JSON_THROW_ON_ERROR,
		);

		$this->assertSame('0301', $response['id']);
		$this->assertSame(1, $response['day']);
		$this->assertSame(3, $response['month']);
		$this->assertCount(1, $response['holidays']);
		$this->assertSame('March First', $response['holidays'][0]['name']);
	}

	/**
	 * @throws JsonException
	 */
	public function testGetFloatingHolidaysReturnsList(): void
	{
		$this->request('GET', '/v2/holiday/en/floating');

		$this->assertResponseIsSuccessful();
		$response = json_decode(
			$this->client->getResponse()->getContent(),
			true,
			512,
			JSON_THROW_ON_ERROR,
		);

		$this->assertIsArray($response);
		$this->assertCount(1, $response);
		$this->assertSame('Floating Test Day', $response[0]['name']);
		$this->assertArrayHasKey('script', $response[0]);
	}

	/**
	 * @throws JsonException
	 */
	public function testGetFixedHolidaysReturnsList(): void
	{
		$this->request('GET', '/v2/holiday/en/fixed');

		$this->assertResponseIsSuccessful();
		$response = json_decode(
			$this->client->getResponse()->getContent(),
			true,
			512,
			JSON_THROW_ON_ERROR,
		);

		$this->assertIsArray($response);
		$this->assertCount(1, $response);
		$this->assertSame('0301', $response[0]['id']);
		$this->assertSame(1, $response[0]['day']);
		$this->assertSame(3, $response[0]['month']);
		$this->assertCount(1, $response[0]['holidays']);
		$this->assertSame('March First', $response[0]['holidays'][0]['name']);
	}
}
