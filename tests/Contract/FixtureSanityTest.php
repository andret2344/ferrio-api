<?php

namespace App\Tests\Contract;

use FilesystemIterator;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Authoring sanity check for the contract fixtures. Complements, but does not overlap, the other
 * two guards:
 *
 * - the git append-only CI job enforces that a frozen file is never modified — but says nothing
 *   about whether a newly *added* file is well-formed;
 * - the per-endpoint contract tests exercise schemas against real responses — but boot the kernel
 *   and DB and give cryptic errors when a schema is simply broken.
 *
 * This test fails fast, with no kernel/DB, when a fixture is malformed:
 *
 * - every `*.json` (request fixtures included) decodes as JSON;
 * - every `*.schema.json` uses draft-07 keywords with the right shapes (`type` is a known type or
 *   list of them, `required` is a list of strings, `enum` is a non-empty list, `properties` is an
 *   object, `items` is a schema or list of schemas).
 *
 * Out of scope by design: a schema that is valid but too loose, and a misspelled keyword (an
 * unknown keyword is legal and ignored in draft-07). Those cannot be caught structurally.
 */
final class FixtureSanityTest extends TestCase
{
	private const string DATA_DIR = __DIR__ . '/data';

	private const array VALID_TYPES = ['null', 'boolean', 'object', 'array', 'number', 'string', 'integer'];

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function jsonFiles(): iterable
	{
		yield from self::files('.json');
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function schemaFiles(): iterable
	{
		yield from self::files('.schema.json');
	}

	#[DataProvider('jsonFiles')]
	public function testFixtureIsValidJson(string $path): void
	{
		$content = file_get_contents($path);
		self::assertIsString($content);
		try {
			$data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			self::fail(sprintf('Invalid JSON in %s: %s', self::relative($path), $exception->getMessage()));
		}
		self::assertIsArray($data, sprintf('%s must be a JSON object/array', self::relative($path)));
	}

	/**
	 * @throws JsonException
	 */
	#[DataProvider('schemaFiles')]
	public function testSchemaIsStructurallyValidDraft07(string $path): void
	{
		$schema = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
		$relative = self::relative($path);
		self::assertIsArray($schema, "$relative is not a JSON object");
		self::assertSame(
			'http://json-schema.org/draft-07/schema#',
			$schema['$schema'] ?? null,
			"$relative must declare the draft-07 \$schema"
		);
		$this->assertValidSchemaNode($schema, $relative, '#');
	}

	private function assertValidSchemaNode(mixed $node, string $file, string $pointer): void
	{
		if (is_bool($node)) {
			return;
		}
		self::assertIsArray($node, "$file: schema at $pointer must be an object or boolean");

		if (array_key_exists('type', $node)) {
			$this->assertValidType($node['type'], $file, $pointer);
		}
		if (array_key_exists('required', $node)) {
			self::assertTrue(
				self::isListOfStrings($node['required']),
				"$file: 'required' at $pointer must be an array of strings"
			);
		}
		if (array_key_exists('enum', $node)) {
			self::assertTrue(
				is_array($node['enum']) && $node['enum'] !== [] && array_is_list($node['enum']),
				"$file: 'enum' at $pointer must be a non-empty array"
			);
		}
		if (array_key_exists('properties', $node)) {
			self::assertIsArray($node['properties'], "$file: 'properties' at $pointer must be an object");
			foreach ($node['properties'] as $name => $sub) {
				$this->assertValidSchemaNode($sub, $file, "$pointer/properties/$name");
			}
		}
		if (array_key_exists('items', $node)) {
			$items = $node['items'];
			if (is_array($items) && array_is_list($items)) {
				foreach ($items as $index => $sub) {
					$this->assertValidSchemaNode($sub, $file, "$pointer/items/$index");
				}
			} else {
				$this->assertValidSchemaNode($items, $file, "$pointer/items");
			}
		}
	}

	private function assertValidType(mixed $type, string $file, string $pointer): void
	{
		if (is_string($type)) {
			self::assertContains($type, self::VALID_TYPES, "$file: invalid type '$type' at $pointer");
			return;
		}
		self::assertTrue(
			self::isListOfStrings($type),
			"$file: 'type' at $pointer must be a type string or an array of type strings"
		);
		foreach ($type as $single) {
			self::assertContains($single, self::VALID_TYPES, "$file: invalid type '$single' at $pointer");
		}
	}

	private static function isListOfStrings(mixed $value): bool
	{
		if (!is_array($value) || !array_is_list($value)) {
			return false;
		}
		foreach ($value as $item) {
			if (!is_string($item)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	private static function files(string $suffix): iterable
	{
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(self::DATA_DIR, FilesystemIterator::SKIP_DOTS)
		);
		foreach ($iterator as $file) {
			$path = $file->getPathname();
			if (str_ends_with($path, $suffix)) {
				yield self::relative($path) => [$path];
			}
		}
	}

	private static function relative(string $path): string
	{
		return str_replace('\\', '/', substr($path, strlen(self::DATA_DIR) + 1));
	}
}
