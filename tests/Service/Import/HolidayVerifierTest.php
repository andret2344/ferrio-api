<?php

namespace App\Tests\Service\Import;

use App\Service\Import\HolidayVerifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class HolidayVerifierTest extends TestCase
{
	/** @var array<string, mixed>|null */
	private ?array $capturedBody = null;

	/** @var list<string> */
	private array $capturedHeaders = [];

	public function testSendsTheHolidayWithWebSearchAndFallbacks(): void
	{
		$verifier = $this->verifier(new MockResponse(self::answer([self::verdictBlock()])));

		$verifier->verify('Dzień Kota', [['day' => 17, 'month' => 2, 'count' => 2], ['day' => 8, 'month' => 8, 'count' => 1]]);

		self::assertSame('claude-opus-5-5', $this->capturedBody['model']);
		self::assertSame('default', $this->capturedBody['fallbacks']);
		self::assertSame('web_search', $this->capturedBody['tools'][0]['name']);
		self::assertStringContainsString('Dzień Kota', $this->capturedBody['messages'][0]['content']);
		self::assertStringContainsString('17.2, 8.8', $this->capturedBody['messages'][0]['content']);
		self::assertStringContainsString('niezależność', $this->capturedBody['system']);
		self::assertContains('anthropic-beta: server-side-fallback-2026-07-01', $this->capturedHeaders);
	}

	public function testReadsTheVerdictAndFlagsWhichSourcesTheSearchReturned(): void
	{
		$verdict = '```json' . "\n" . json_encode([
			'exists' => 'yes',
			'confidence' => 'high',
			'date' => '17.2',
			'date_note' => null,
			'origin' => 'Ustanowiony w 2002 r. przez redakcję magazynu "Kot".',
			'summary' => 'Dwa niezależne źródła.',
			'sources' => [
				['url' => 'https://example.org/kot', 'kind' => 'media'],
				['url' => 'https://made-up.example/kot', 'kind' => 'authority'],
				['url' => 'javascript:alert(1)', 'kind' => 'other'],
				['url' => 'https://blog.example/kot', 'kind' => 'nonsense'],
			],
		]) . "\n```";
		// The search tool filters its results through code execution, so the answer carries no
		// citations - the result blocks are what tells a page the search found from an invented one.
		$verifier = $this->verifier(new MockResponse(self::answer([
			['type' => 'server_tool_use', 'name' => 'web_search'],
			['type' => 'web_search_tool_result', 'content' => [
				['type' => 'web_search_result', 'url' => 'https://example.org/kot', 'title' => 'Kot'],
				['type' => 'web_search_result', 'url' => 'https://blog.example/kot/', 'title' => 'Blog'],
				['type' => 'web_search_result', 'url' => 'https://never-named.example/kot', 'title' => 'Other'],
			]],
			['type' => 'code_execution_tool_result', 'content' => []],
			['type' => 'text', 'text' => $verdict],
		])));

		$result = $verifier->verify('Dzień Kota', [['day' => 17, 'month' => 2, 'count' => 1]]);

		self::assertSame('yes', $result['exists']);
		self::assertSame('high', $result['confidence']);
		self::assertSame('17.2', $result['date']);
		self::assertNull($result['date_note']);
		self::assertSame([
			['url' => 'https://example.org/kot', 'kind' => 'media', 'searched' => true],
			['url' => 'https://made-up.example/kot', 'kind' => 'authority', 'searched' => false],
			// The search returned it with a trailing slash; the model dropped it.
			['url' => 'https://blog.example/kot', 'kind' => 'other', 'searched' => true],
		], $result['sources']);
	}

	public function testCollectsSearchResultsAcrossAPausedTurn(): void
	{
		$paused = [
			['type' => 'server_tool_use', 'name' => 'web_search'],
			['type' => 'web_search_tool_result', 'content' => [
				['type' => 'web_search_result', 'url' => 'https://example.org/kot', 'title' => 'Kot'],
			]],
		];
		$verdict = json_encode(['exists' => 'yes', 'confidence' => 'medium', 'summary' => 'x', 'sources' => [
			['url' => 'https://example.org/kot', 'kind' => 'media'],
		]]);
		$responses = [
			new MockResponse(json_encode(['stop_reason' => 'pause_turn', 'content' => $paused])),
			new MockResponse(self::answer([['type' => 'text', 'text' => $verdict]])),
		];
		$verifier = new HolidayVerifier(new MockHttpClient(function () use (&$responses): MockResponse {
			return array_shift($responses);
		}), 'test-key');

		$result = $verifier->verify('Dzień Kota', []);

		self::assertTrue($result['sources'][0]['searched']);
	}

	public function testAFailedSearchContributesNoResults(): void
	{
		$verdict = json_encode(['exists' => 'unclear', 'confidence' => 'low', 'summary' => 'x', 'sources' => [
			['url' => 'https://example.org/kot', 'kind' => 'media'],
		]]);
		$verifier = $this->verifier(new MockResponse(self::answer([
			['type' => 'web_search_tool_result', 'content' => ['type' => 'web_search_tool_result_error', 'error_code' => 'max_uses_exceeded']],
			['type' => 'text', 'text' => $verdict],
		])));

		$result = $verifier->verify('Dzień Kota', []);

		self::assertFalse($result['sources'][0]['searched']);
	}

	public function testUnknownValuesFallBackToTheCautiousOnes(): void
	{
		$verifier = $this->verifier(new MockResponse(self::answer([
			['type' => 'text', 'text' => json_encode(['exists' => 'maybe', 'confidence' => 'total', 'date' => 'mid-May', 'summary' => 'x'])],
		])));

		$result = $verifier->verify('Dzień Kota', []);

		self::assertSame('unclear', $result['exists']);
		self::assertSame('low', $result['confidence']);
		self::assertNull($result['date']);
		self::assertSame([], $result['sources']);
	}

	public function testApiErrorIsReported(): void
	{
		$verifier = $this->verifier(new MockResponse(
			json_encode(['error' => ['message' => 'Overloaded']]),
			['http_code' => 529]
		));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Overloaded');
		$verifier->verify('Dzień Kota', []);
	}

	public function testRefusalIsReported(): void
	{
		$verifier = $this->verifier(new MockResponse(json_encode(['stop_reason' => 'refusal', 'content' => []])));

		$this->expectException(RuntimeException::class);
		$verifier->verify('Dzień Kota', []);
	}

	public function testProseWithoutAnObjectIsReported(): void
	{
		$verifier = $this->verifier(new MockResponse(self::answer([['type' => 'text', 'text' => 'Nie wiem.']])));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('did not return a verdict');
		$verifier->verify('Dzień Kota', []);
	}

	public function testMissingApiKeyIsReportedWithoutARequest(): void
	{
		$verifier = new HolidayVerifier(new MockHttpClient(function (): MockResponse {
			self::fail('No request expected.');
		}), '');

		$this->expectException(RuntimeException::class);
		$verifier->verify('Dzień Kota', []);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function verdictBlock(): array
	{
		return ['type' => 'text', 'text' => json_encode(['exists' => 'no', 'confidence' => 'low', 'summary' => 'Tylko blogi.', 'sources' => []])];
	}

	/**
	 * @param list<array<string, mixed>> $content
	 */
	private static function answer(array $content): string
	{
		return json_encode(['stop_reason' => 'end_turn', 'content' => $content]);
	}

	private function verifier(MockResponse $response): HolidayVerifier
	{
		$client = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
			$this->capturedBody = json_decode($options['body'], true);
			$this->capturedHeaders = $options['headers'];
			return $response;
		});
		return new HolidayVerifier($client, 'test-key');
	}
}
