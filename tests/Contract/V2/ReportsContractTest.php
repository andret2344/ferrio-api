<?php

namespace App\Tests\Contract\V2;

use App\Entity\FixedHolidayMetadata;
use App\Entity\FloatingHolidayMetadata;
use App\Tests\Contract\ContractTestCase;
use App\Tests\Fixture\BanFixture;
use App\Tests\Fixture\CountryFixture;
use App\Tests\Fixture\FixedHolidayMetadataFixture;
use App\Tests\Fixture\FloatingHolidayMetadataFixture;
use App\Tests\Fixture\LanguageFixture;
use JsonException;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unified contract for the v2 user-report endpoints, covering the full
 * {error, suggestion} × {fixed, floating} matrix from one driver:
 *
 * - error      → POST /{version}/report/{holidayType}   + GET /{version}/report/{userId}/{holidayType}
 * - suggestion → POST /{version}/missing/{holidayType}  + GET /{version}/missing/{userId}/{holidayType}
 *
 * Only the driver is unified — the frozen request/schema fixtures under data/v2/* stay per-combo and
 * append-only (a new field drops in a new genNN pair; old generations are replayed forever). The error
 * endpoint is forward-aliased to v3 (same `ReportControllerV2`), so its frozen generations are replayed
 * against BOTH /v2/report and /v3/report — that proves the alias never drifted the shape. `missing` is
 * deliberately v2-only.
 */
class ReportsContractTest extends ContractTestCase
{
	/**
	 * name => [contractDir, endpoint (report|missing), holidayType, needsMetadata].
	 */
	private const array COMBOS = [
		'fixed_error' => ['v2/fixed_error', 'report', 'fixed', true],
		'floating_error' => ['v2/floating_error', 'report', 'floating', true],
		'fixed_suggestion' => ['v2/fixed_suggestion', 'missing', 'fixed', false],
		'floating_suggestion' => ['v2/floating_suggestion', 'missing', 'floating', false],
	];

	private const array GENERATIONS = ['gen01_baseline', 'gen02_device'];

	private AbstractDatabaseTool $databaseTool;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->client = static::createClient();

		$this->databaseTool = static::getContainer()
			->get(DatabaseToolCollection::class)
			->get();

		// Superset of what any combo needs — extra fixtures are harmless for the suggestion combos.
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
	 * combo × applicable-version × generation. The error endpoint is aliased to v3, so it runs on
	 * both versions; the missing endpoint is v2-only.
	 *
	 * @return iterable<string, array{string, string, string, bool, string, string}>
	 */
	public static function generations(): iterable
	{
		foreach (self::COMBOS as $name => [$dir, $endpoint, $holidayType, $needsMetadata]) {
			$versions = $endpoint === 'report' ? ['v2', 'v3'] : ['v2'];
			foreach ($versions as $version) {
				foreach (self::GENERATIONS as $generation) {
					yield "$name $version $generation" => [$dir, $endpoint, $holidayType, $needsMetadata, $version, $generation];
				}
			}
		}
	}

	/**
	 * @return iterable<string, array{string, string, string, bool}>
	 */
	public static function combos(): iterable
	{
		foreach (self::COMBOS as $name => $spec) {
			yield $name => $spec;
		}
	}

	/**
	 * @throws JsonException
	 */
	#[DataProvider('generations')]
	public function testGenerationContract(string $dir, string $endpoint, string $holidayType, bool $needsMetadata, string $version, string $generation): void
	{
		$payload = $this->loadRequest("$dir/$generation.json");
		if ($needsMetadata) {
			// metadata is a DB foreign key, unknowable when the fixture was frozen — inject the live id.
			$payload['metadata'] = $this->metadataId($holidayType);
		}
		$userId = $payload['user_id'];

		$this->request('POST', "/$version/$endpoint/$holidayType", [], $payload);
		$this->assertResponseStatusCodeSame(201);

		$this->request('GET', "/$version/$endpoint/$userId/$holidayType");
		$this->assertResponseStatusCodeSame(200);
		$this->assertResponseMatchesContract("$dir/$generation.schema.json");
	}

	/**
	 * @throws JsonException
	 */
	#[DataProvider('combos')]
	public function testRejectsBannedUser(string $dir, string $endpoint, string $holidayType, bool $needsMetadata): void
	{
		$payload = $this->loadRequest("$dir/reject_banned.json");
		if ($needsMetadata) {
			$payload['metadata'] = $this->metadataId($holidayType);
		}

		$this->request('POST', "/v2/$endpoint/$holidayType", [], $payload);
		$this->assertResponseStatusCodeSame(403);
		$this->assertResponseMatchesContract("$dir/reject_banned.schema.json");
	}

	/**
	 * @throws JsonException
	 */
	#[DataProvider('combos')]
	public function testRejectsMissingRequiredField(string $dir, string $endpoint, string $holidayType, bool $needsMetadata): void
	{
		$payload = $this->loadRequest("$dir/reject_missing_required.json");

		$this->request('POST', "/v2/$endpoint/$holidayType", [], $payload);
		$this->assertResponseStatusCodeSame(422);
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
