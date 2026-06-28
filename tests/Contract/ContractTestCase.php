<?php

namespace App\Tests\Contract;

use App\Tests\Trait\TestUtilTrait;
use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use JsonException;
use JsonSchema\Validator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base class for the append-only contract regression suite.
 *
 * Each public endpoint owns a folder under {@see DATA_DIR}/<version>/<endpoint> containing one
 * frozen pair per client *generation*:
 *
 * - `genNN[_label].json`        — the exact request body a client of that generation sends.
 * - `genNN[_label].schema.json` — the response shape that generation relies on (draft-07).
 *
 * The rule: a new field NEVER edits an existing generation. You drop in a new `genNN` pair. Old
 * generations stay forever and keep being replayed, which is what proves "an old client still
 * works": its literal request is still accepted and the response still satisfies the shape it
 * was built against. Response schemas omit {@code additionalProperties: false} on purpose, so an
 * added field is invisible to older generations (forward-compat) while a removed/renamed/retyped
 * field breaks every generation that declared it.
 */
abstract class ContractTestCase extends WebTestCase
{
	use TestUtilTrait;

	private const string DATA_DIR = __DIR__ . '/data';

	/**
	 * Loaded fixtures, declared here (not in subclasses) so the {@see TestUtilTrait::getFixture()}
	 * helper — flattened into this class's scope — can read it on any subclass instance.
	 */
	protected AbstractExecutor $fixtures;

	/**
	 * Decodes a frozen request fixture into an associative array so the caller can inject the
	 * runtime-only foreign keys it cannot know at authoring time (e.g. a fixture-loaded metadata
	 * id). The on-disk file stays a literal snapshot of the wire payload; only DB surrogate ids
	 * are overridden before sending.
	 *
	 * @return array<string, mixed>
	 * @throws JsonException
	 */
	protected function loadRequest(string $relativePath): array
	{
		$path = self::DATA_DIR . '/' . $relativePath;
		$json = file_get_contents($path);
		self::assertIsString($json, "Missing contract request fixture: $relativePath");
		return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
	}

	/**
	 * Validates the current JSON response against a frozen contract schema. Extra fields are
	 * allowed (forward-compat); a missing required field, a renamed field or a changed type fails.
	 *
	 * @throws JsonException
	 */
	protected function assertResponseMatchesContract(string $relativePath): void
	{
		$body = $this->client->getResponse()
			->getContent();
		self::assertIsString($body);
		$data = json_decode($body, false, 512, JSON_THROW_ON_ERROR);

		$schemaPath = self::DATA_DIR . '/' . $relativePath;
		$schemaJson = file_get_contents($schemaPath);
		self::assertIsString($schemaJson, "Missing contract schema: $relativePath");
		$schema = json_decode($schemaJson, false, 512, JSON_THROW_ON_ERROR);

		$validator = new Validator();
		$validator->validate($data, $schema);

		if (!$validator->isValid()) {
			$errors = array_map(
				static fn(array $error): string => sprintf('[%s] %s', $error['property'], $error['message']),
				$validator->getErrors()
			);
			self::fail("Response violates contract $relativePath:\n" . implode("\n", $errors));
		}
	}
}
