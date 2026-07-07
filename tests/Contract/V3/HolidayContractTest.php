<?php

namespace App\Tests\Contract\V3;

use App\Tests\Contract\ContractTestCase;
use App\Tests\Fixture\FixedHolidayFixture;
use App\Tests\Fixture\FloatingHolidayFixture;
use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use JsonException;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Contract for the public v3 holidays list: GET /v3/holidays?lang={code}.
 *
 * gen01 is the pre-AI client — the flat-item shape before holiday content was AI-labelled.
 * gen02 is the AI-era client, which additionally relies on the {@code ai_generated} flag. Both
 * are replayed against the current response: gen01 stays valid because schemas omit
 * {@code additionalProperties: false} (the added field is invisible to the old generation), while
 * gen02 requires it. A removed/renamed/retyped field breaks whichever generation declared it.
 */
class HolidayContractTest extends ContractTestCase
{
	private const string CONTRACT = 'v3/holidays';

	private AbstractDatabaseTool $databaseTool;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->client = static::createClient();

		$this->databaseTool = static::getContainer()
			->get(DatabaseToolCollection::class)
			->get();

		$this->fixtures = $this->databaseTool->loadFixtures([
			FixedHolidayFixture::class,
			FloatingHolidayFixture::class,
		]);
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function generations(): iterable
	{
		yield 'gen01 - pre-AI client' => ['gen01_baseline'];
		yield 'gen02 - AI-labelled client' => ['gen02_ai'];
	}

	/**
	 * @throws JsonException
	 */
	#[DataProvider('generations')]
	public function testFlatListContract(string $generation): void
	{
		$this->request('GET', '/v3/holidays', ['lang' => 'en', 'year' => 2026, 'includeMatureContent' => 'true']);

		$this->assertResponseStatusCodeSame(200);
		$this->assertResponseMatchesContract(self::CONTRACT . "/$generation.schema.json");
	}
}
