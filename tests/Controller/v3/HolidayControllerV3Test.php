<?php

namespace App\Tests\Controller\v3;

use App\Tests\Fixture\FixedHolidayFixture;
use App\Tests\Fixture\FloatingHolidayFixture;
use App\Tests\Trait\TestUtilTrait;
use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HolidayControllerV3Test extends WebTestCase
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

	public function testGetAllReturnsUnifiedFlatList(): void
	{
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		// 1 fixed (0301, matureContent=false) + 1 floating; 0314 is excluded (matureContent=true)
		$this->assertCount(2, $response);

		// All items should have the unified shape
		foreach ($response as $holiday) {
			$this->assertArrayHasKey('id', $holiday);
			$this->assertArrayHasKey('day', $holiday);
			$this->assertArrayHasKey('month', $holiday);
			$this->assertArrayHasKey('name', $holiday);
			$this->assertArrayHasKey('usual', $holiday);
			$this->assertArrayHasKey('description', $holiday);
			$this->assertArrayHasKey('country', $holiday);
			$this->assertArrayHasKey('url', $holiday);
			$this->assertArrayHasKey('mature_content', $holiday);
			// No 'script' key in v3
			$this->assertArrayNotHasKey('script', $holiday);
		}
	}

	public function testGetAllReturnsSortedByDate(): void
	{
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		// Verify sorted by month then day
		for ($i = 1; $i < count($response); $i++) {
			$prev = [$response[$i - 1]['month'], $response[$i - 1]['day']];
			$curr = [$response[$i]['month'], $response[$i]['day']];
			$this->assertLessThanOrEqual(0, $prev <=> $curr, 'Holidays should be sorted by month/day');
		}
	}

	public function testGetAllReturnsFixedHolidaysWithPrefixedId(): void
	{
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$fixedIds = array_filter(array_column($response, 'id'), fn(string $id) => str_starts_with($id, 'fixed-'));
		$this->assertCount(1, $fixedIds);
	}

	public function testGetAllReturnsFloatingHolidaysWithResolvedDates(): void
	{
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$floating = array_values(array_filter($response, fn(array $h) => str_starts_with($h['id'], 'floating-')));
		$this->assertCount(1, $floating);
		// Hardcoded date for 2026 is "15.4" → day 15, month 4
		$this->assertSame(15, $floating[0]['day']);
		$this->assertSame(4, $floating[0]['month']);
		$this->assertSame('Floating Test Day', $floating[0]['name']);
	}

	public function testGetAllWithDifferentYear(): void
	{
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'year' => 2025]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$floating = array_values(array_filter($response, fn(array $h) => str_starts_with($h['id'], 'floating-')));
		$this->assertCount(1, $floating);
		// Hardcoded date for 2025 is "14.4" → day 14, month 4
		$this->assertSame(14, $floating[0]['day']);
		$this->assertSame(4, $floating[0]['month']);
	}

	public function testFilterByDayAndMonth(): void
	{
		// March 1st — should match the fixed holiday on that date
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'day' => 1, 'month' => 3, 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertCount(1, $response);
		$this->assertSame('March First', $response[0]['name']);
		$this->assertSame(1, $response[0]['day']);
		$this->assertSame(3, $response[0]['month']);
		$this->assertStringStartsWith('fixed-', $response[0]['id']);
	}

	public function testFilterByDayAndMonthFloating(): void
	{
		// April 15th — the floating holiday resolves to this date for 2026
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'day' => 15, 'month' => 4, 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertCount(1, $response);
		$this->assertSame('Floating Test Day', $response[0]['name']);
		$this->assertStringStartsWith('floating-', $response[0]['id']);
	}

	public function testFilterByDayAndMonthNoMatch(): void
	{
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'day' => 25, 'month' => 12, 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertCount(0, $response);
	}

	public function testFilterByMonthOnly(): void
	{
		// Month 3 — should return the fixed holiday on March 1st
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'month' => 3, 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertCount(1, $response);
		$this->assertSame(3, $response[0]['month']);
		$this->assertSame('March First', $response[0]['name']);
	}

	public function testFilterByMonthOnlyNoMatch(): void
	{
		// Month 12 — no holidays in December
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'month' => 12, 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertCount(0, $response);
	}

	public function testFilterByDayOnly(): void
	{
		// Day 15 — should return the floating holiday (April 15 for 2026)
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'day' => 15, 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertCount(1, $response);
		$this->assertSame(15, $response[0]['day']);
		$this->assertSame('Floating Test Day', $response[0]['name']);
	}

	public function testFilterByDayOnlyNoMatch(): void
	{
		// Day 28 — no holidays on the 28th
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'day' => 28, 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertCount(0, $response);
	}

	public function testFilterByCountry(): void
	{
		// March 1st fixed holiday has country=GB
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'country' => 'GB', 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertCount(1, $response);
		$this->assertSame('March First', $response[0]['name']);
		$this->assertSame('GB', $response[0]['country']);
	}

	public function testFilterByCountryNoMatch(): void
	{
		// No holidays with country=PL
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'country' => 'PL', 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertCount(0, $response);
	}

	public function testFilterByCountryCombinedWithDay(): void
	{
		// Day 1 + country GB → March 1st
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'day' => 1, 'country' => 'GB', 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertCount(1, $response);
		$this->assertSame('March First', $response[0]['name']);
	}

	public function testUnknownLanguageReturnsEmptyArray(): void
	{
		$this->request('GET', '/v3/holidays', ['lang' => 'xx', 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertCount(0, $response);
	}

	public function testMissingLangReturns400(): void
	{
		$this->request('GET', '/v3/holidays', ['year' => 2026]);

		$this->assertResponseStatusCodeSame(400);
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertSame('Missing required query parameter: lang', $response['error']);
	}

	public function testLangIsCaseInsensitive(): void
	{
		$this->request('GET', '/v3/holidays', ['lang' => 'EN', 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertCount(2, $response);
	}

	public function testCountryIsCaseInsensitive(): void
	{
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'country' => 'gb', 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertCount(1, $response);
		$this->assertSame('GB', $response[0]['country']);
	}

	public function testGroupingReturnsDayObjects(): void
	{
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'year' => 2026, 'grouping' => 'true']);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		// 2 holidays on different days → 2 groups
		$this->assertCount(2, $response);

		foreach ($response as $group) {
			$this->assertArrayHasKey('id', $group);
			$this->assertArrayHasKey('day', $group);
			$this->assertArrayHasKey('month', $group);
			$this->assertArrayHasKey('holidays', $group);
			$this->assertIsArray($group['holidays']);
		}

		// First group: March 1st (fixed)
		$this->assertSame('0301', $response[0]['id']);
		$this->assertSame(1, $response[0]['day']);
		$this->assertSame(3, $response[0]['month']);
		$this->assertCount(1, $response[0]['holidays']);
		$this->assertSame('March First', $response[0]['holidays'][0]['name']);

		// Second group: April 15th (floating)
		$this->assertSame('0415', $response[1]['id']);
		$this->assertSame(15, $response[1]['day']);
		$this->assertSame(4, $response[1]['month']);
		$this->assertCount(1, $response[1]['holidays']);
		$this->assertSame('Floating Test Day', $response[1]['holidays'][0]['name']);
	}

	public function testGroupingDefaultsToFalse(): void
	{
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'year' => 2026]);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		// Flat list, not grouped
		$this->assertArrayNotHasKey('holidays', $response[0]);
		$this->assertArrayHasKey('name', $response[0]);
	}

	public function testGroupingCombinedWithFilters(): void
	{
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'year' => 2026, 'month' => 3, 'grouping' => 'true']);

		$this->assertResponseIsSuccessful();
		$response = json_decode($this->client->getResponse()
			->getContent(), true);

		$this->assertCount(1, $response);
		$this->assertSame('0301', $response[0]['id']);
		$this->assertCount(1, $response[0]['holidays']);
	}
}
