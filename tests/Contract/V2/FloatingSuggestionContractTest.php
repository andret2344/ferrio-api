<?php

namespace App\Tests\Contract\V2;

use App\Tests\Contract\ContractTestCase;
use App\Tests\Fixture\BanFixture;
use App\Tests\Fixture\CountryFixture;
use JsonException;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Contract for the floating suggestion endpoint: POST /v2/missing/floating + GET /v2/missing/{userId}/floating.
 */
class FloatingSuggestionContractTest extends ContractTestCase
{
	private const string CONTRACT = 'v2/floating_suggestion';

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
			CountryFixture::class,
			BanFixture::class,
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
		yield 'gen01 - pre-device client' => ['gen01_baseline'];
		yield 'gen02 - device client' => ['gen02_device'];
	}

	/**
	 * @throws JsonException
	 */
	#[DataProvider('generations')]
	public function testGenerationContract(string $generation): void
	{
		$payload = $this->loadRequest(self::CONTRACT . "/$generation.json");
		$userId = $payload['user_id'];

		$this->request('POST', '/v2/missing/floating', [], $payload);
		$this->assertResponseStatusCodeSame(201);

		$this->request('GET', "/v2/missing/$userId/floating");
		$this->assertResponseStatusCodeSame(200);
		$this->assertResponseMatchesContract(self::CONTRACT . "/$generation.schema.json");
	}

	/**
	 * @throws JsonException
	 */
	public function testRejectsBannedUser(): void
	{
		$payload = $this->loadRequest(self::CONTRACT . '/reject_banned.json');

		$this->request('POST', '/v2/missing/floating', [], $payload);
		$this->assertResponseStatusCodeSame(403);
		$this->assertResponseMatchesContract(self::CONTRACT . '/reject_banned.schema.json');
	}

	/**
	 * @throws JsonException
	 */
	public function testRejectsMissingRequiredField(): void
	{
		$payload = $this->loadRequest(self::CONTRACT . '/reject_missing_required.json');

		$this->request('POST', '/v2/missing/floating', [], $payload);
		$this->assertResponseStatusCodeSame(422);
	}
}
