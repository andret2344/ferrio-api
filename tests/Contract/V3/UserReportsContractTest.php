<?php

namespace App\Tests\Contract\V3;

use App\Entity\FixedHolidayMetadata;
use App\Entity\FloatingHolidayMetadata;
use App\Tests\Contract\ContractTestCase;
use App\Tests\Fixture\BanFixture;
use App\Tests\Fixture\CountryFixture;
use App\Tests\Fixture\FixedHolidayMetadataFixture;
use App\Tests\Fixture\FloatingHolidayMetadataFixture;
use App\Tests\Fixture\LanguageFixture;
use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use JsonException;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Contract for the single authenticated v3 reports endpoint:
 * GET/POST /v3/users/reports?reportType={error|suggestion}&holidayType={fixed|floating}.
 *
 * Unlike v2 the user id comes from the Firebase token (test stub: `test-token` -> `user-id`,
 * `banned-token` -> banned, `invalid-token`/`anonymous-token`/no header -> 401), so request
 * fixtures carry no `user_id`. The error/auth bodies here are controller-authored, so we pin
 * their shapes (403 `{reason}`, 422 `{errors:[{property,message}]}`, 401 `{error}`).
 */
class UserReportsContractTest extends ContractTestCase
{
	private const string CONTRACT = 'v3/users_reports';
	private const array AUTH = ['Authorization' => 'Bearer test-token'];

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
			LanguageFixture::class,
			CountryFixture::class,
			FixedHolidayMetadataFixture::class,
			FloatingHolidayMetadataFixture::class,
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
	 * @return iterable<string, array{string, string, string, string}>
	 */
	public static function generations(): iterable
	{
		$combos = [
			'error_fixed' => ['error', 'fixed'],
			'error_floating' => ['error', 'floating'],
			'suggestion_fixed' => ['suggestion', 'fixed'],
			'suggestion_floating' => ['suggestion', 'floating'],
		];
		foreach ($combos as $combo => [$reportType, $holidayType]) {
			yield "$combo gen01 - pre-device client" => [$combo, $reportType, $holidayType, 'gen01_baseline'];
			yield "$combo gen02 - device client" => [$combo, $reportType, $holidayType, 'gen02_device'];
		}
	}

	/**
	 * @throws JsonException
	 */
	#[DataProvider('generations')]
	public function testGenerationContract(string $combo, string $reportType, string $holidayType, string $generation): void
	{
		$query = ['reportType' => $reportType, 'holidayType' => $holidayType];

		$payload = $this->loadRequest(self::CONTRACT . "/$combo/$generation.json");
		if ($reportType === 'error') {
			// metadata is a DB foreign key, unknowable when the fixture was frozen — inject the live id.
			$payload['metadata'] = $this->metadataId($holidayType);
		}

		$this->request('POST', '/v3/users/reports', $query, $payload, self::AUTH);
		$this->assertResponseStatusCodeSame(201);

		$this->request('GET', '/v3/users/reports', $query, [], self::AUTH);
		$this->assertResponseStatusCodeSame(200);
		$this->assertResponseMatchesContract(self::CONTRACT . "/$combo/$generation.schema.json");
	}

	/**
	 * @throws JsonException
	 */
	public function testRequestWithoutTokenReturns401(): void
	{
		$this->request('GET', '/v3/users/reports', ['reportType' => 'error', 'holidayType' => 'fixed']);
		$this->assertResponseStatusCodeSame(401);
	}

	/**
	 * @throws JsonException
	 */
	public function testInvalidTokenReturns401(): void
	{
		$this->request('GET', '/v3/users/reports', ['reportType' => 'error', 'holidayType' => 'fixed'], [], ['Authorization' => 'Bearer invalid-token']);
		$this->assertResponseStatusCodeSame(401);
		$this->assertResponseMatchesContract(self::CONTRACT . '/reject_invalid_token.schema.json');
	}

	/**
	 * @throws JsonException
	 */
	public function testAnonymousTokenReturns401(): void
	{
		$this->request('GET', '/v3/users/reports', ['reportType' => 'error', 'holidayType' => 'fixed'], [], ['Authorization' => 'Bearer anonymous-token']);
		$this->assertResponseStatusCodeSame(401);
	}

	/**
	 * @throws JsonException
	 */
	public function testRejectsBannedUser(): void
	{
		$payload = $this->loadRequest(self::CONTRACT . '/reject_banned.json');
		$payload['metadata'] = $this->metadataId('fixed');

		$this->request('POST', '/v3/users/reports', ['reportType' => 'error', 'holidayType' => 'fixed'], $payload, ['Authorization' => 'Bearer banned-token']);
		$this->assertResponseStatusCodeSame(403);
		$this->assertResponseMatchesContract(self::CONTRACT . '/reject_banned.schema.json');
	}

	/**
	 * A body that denormalizes but violates a constraint (here an invalid `report_type`) is the
	 * v3 validation contract: 422 with a controller-authored `{errors: [{property, message}]}`
	 * list. (A body *missing* a constructor-required field is a different path — it fails during
	 * denormalization, not validation — so it is not covered here.)
	 *
	 * @throws JsonException
	 */
	public function testRejectsInvalidField(): void
	{
		$payload = $this->loadRequest(self::CONTRACT . '/reject_invalid_field.json');
		$payload['metadata'] = $this->metadataId('fixed');

		$this->request('POST', '/v3/users/reports', ['reportType' => 'error', 'holidayType' => 'fixed'], $payload, self::AUTH);
		$this->assertResponseStatusCodeSame(422);
		$this->assertResponseMatchesContract(self::CONTRACT . '/reject_invalid_field.schema.json');
	}

	/**
	 * @throws JsonException
	 */
	public function testUnknownReportTypeReturns400(): void
	{
		$payload = $this->loadRequest(self::CONTRACT . '/error_fixed/gen01_baseline.json');
		$payload['metadata'] = $this->metadataId('fixed');

		$this->request('POST', '/v3/users/reports', ['reportType' => 'nonsense', 'holidayType' => 'fixed'], $payload, self::AUTH);
		$this->assertResponseStatusCodeSame(400);
	}

	private function metadataId(string $holidayType): int
	{
		if ($holidayType === 'fixed') {
			/** @var FixedHolidayMetadata $metadata */
			$metadata = $this->getFixture(FixedHolidayMetadataFixture::METADATA_0301, FixedHolidayMetadata::class);
			return $metadata->id;
		}
		/** @var FloatingHolidayMetadata $metadata */
		$metadata = $this->getFixture('floating-holiday-metadata', FloatingHolidayMetadata::class);
		return $metadata->id;
	}
}
