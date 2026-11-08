<?php

namespace App\Service\Import;

use App\Service\WebSearchResults;
use JsonException;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Asks Claude, with Anthropic's server-side web search, whether a holiday from a pasted list exists,
 * how sure that is and where it comes from. One holiday per request, on the admin's click: a whole
 * list would not fit in one HTTP request (a single search-backed answer already needs minutes) and
 * would pay for checks nobody looks at.
 *
 * Confidence is graded on the origin and independence of the sources, not their count - the prompt
 * (config/prompts/verify_system.txt) says so explicitly, because holiday blogs copy each other and an
 * invented holiday can have fifteen identical hits. Each returned source is flagged `searched` when the
 * search really returned that page, which separates the URLs the model read from the ones it merely
 * wrote down. The answer's citations cannot tell them apart: the search tool filters its results
 * through code execution, and an answer written from that output carries none (see WebSearchResults).
 */
class HolidayVerifier
{
	private const string MODEL = 'claude-opus-5-5';

	/**
	 * More searches than the description generator allows: establishing an origin often takes one
	 * search for the name and another for whoever is supposed to have declared it.
	 */
	private const array WEB_SEARCH_TOOL = ['type' => 'web_search_20260209', 'name' => 'web_search', 'max_uses' => 5];

	private const int MAX_CONTINUATIONS = 3;

	/** Same reason as in GenerateDescriptionController: a non-streaming searched answer is slow. */
	private const int TIMEOUT_SECONDS = 180;

	private const array EXISTS = ['yes', 'no', 'unclear'];
	private const array CONFIDENCE = ['high', 'medium', 'low'];
	private const array SOURCE_KINDS = ['authority', 'media', 'encyclopedia', 'calendar_blog', 'other'];

	private readonly string $promptDir;

	public function __construct(
		private readonly HttpClientInterface $httpClient,
		private readonly string              $anthropicApiKey,
		?string                              $promptDir = null,
	)
	{
		$this->promptDir = $promptDir ?? dirname(__DIR__, 3) . '/config/prompts';
	}

	/**
	 * @param list<array{day: int, month: int, count: int}> $dates
	 * @return array{exists: string, confidence: string, date: ?string, date_note: ?string, origin: ?string, summary: string, sources: list<array{url: string, kind: string, cited: bool}>}
	 * @throws RuntimeException with a message fit to show the admin
	 */
	public function verify(string $name, array $dates): array
	{
		if ($this->anthropicApiKey === '') {
			throw new RuntimeException('Anthropic API key is not configured.');
		}

		$dateList = implode(', ', array_map(fn(array $date) => sprintf('%d.%d', $date['day'], $date['month']), $dates));
		if ($dateList === '') {
			$dateList = 'brak';
		}
		$userPrompt = strtr($this->loadPrompt('verify_user'), ['{name}' => $name, '{dates}' => $dateList]);
		$systemPrompt = $this->loadPrompt('verify_system');

		$messages = [['role' => 'user', 'content' => $userPrompt]];
		$searchResults = [];
		for ($continuation = 0; ; $continuation++) {
			$response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
				'headers' => [
					'x-api-key' => $this->anthropicApiKey,
					'anthropic-version' => '2023-06-01',
					// A declined request is re-run on a fallback model chosen by refusal category.
					'anthropic-beta' => 'server-side-fallback-2026-07-01',
					'content-type' => 'application/json',
				],
				'json' => [
					'model' => self::MODEL,
					'max_tokens' => 16000,
					'output_config' => ['effort' => 'medium'],
					'fallbacks' => 'default',
					'system' => $systemPrompt,
					'messages' => $messages,
					'tools' => [self::WEB_SEARCH_TOOL],
				],
				'timeout' => self::TIMEOUT_SECONDS,
			]);

			$result = $response->toArray(false);
			if ($response->getStatusCode() !== 200) {
				throw new RuntimeException((string)($result['error']['message'] ?? 'Unknown API error'));
			}
			if (($result['stop_reason'] ?? null) === 'refusal') {
				throw new RuntimeException('The model declined to check this holiday.');
			}
			// Collected from every response, as a resumed turn may not repeat the searches it paused after.
			$searchResults += WebSearchResults::collect($result['content'] ?? []);
			if (($result['stop_reason'] ?? null) !== 'pause_turn' || $continuation >= self::MAX_CONTINUATIONS) {
				break;
			}
			$messages = [
				['role' => 'user', 'content' => $userPrompt],
				['role' => 'assistant', 'content' => $result['content']],
			];
		}

		$run = self::finalRun($result['content'] ?? []);
		$text = trim(implode('', array_map(fn(array $block) => (string)($block['text'] ?? ''), $run)));
		return self::normalize(self::decode($text), $searchResults);
	}

	/**
	 * The answer is the run of text blocks after the last non-text block (search calls and results,
	 * thinking); it may arrive split into several blocks, joined back by the caller.
	 *
	 * @param list<array<string, mixed>> $content
	 * @return list<array<string, mixed>>
	 */
	private static function finalRun(array $content): array
	{
		$run = [];
		foreach ($content as $block) {
			if (($block['type'] ?? null) !== 'text') {
				$run = [];
				continue;
			}
			$run[] = $block;
		}
		return $run;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function decode(string $text): array
	{
		// Tolerates a stray code fence or a sentence around the object, which the prompt forbids but
		// a model occasionally writes anyway.
		$start = strpos($text, '{');
		$end = strrpos($text, '}');
		if ($start === false || $end === false || $end < $start) {
			throw new RuntimeException('The model did not return a verdict.');
		}
		try {
			$decoded = json_decode(substr($text, $start, $end - $start + 1), true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw new RuntimeException('The model returned a malformed verdict.');
		}
		if (!is_array($decoded)) {
			throw new RuntimeException('The model returned a malformed verdict.');
		}
		return $decoded;
	}

	/**
	 * @param array<string, mixed> $raw
	 * @param array<string, string> $searchResults URL => title, see WebSearchResults::collect()
	 * @return array{exists: string, confidence: string, date: ?string, date_note: ?string, origin: ?string, summary: string, sources: list<array{url: string, kind: string, searched: bool}>}
	 */
	private static function normalize(array $raw, array $searchResults): array
	{
		$sources = [];
		$seen = [];
		$listed = $raw['sources'] ?? null;
		if (!is_array($listed)) {
			$listed = [];
		}
		foreach ($listed as $source) {
			if (!is_array($source)) {
				continue;
			}
			$url = (string)($source['url'] ?? '');
			if (!WebSearchResults::isHttpUrl($url) || isset($seen[$url])) {
				continue;
			}
			$seen[$url] = true;
			$sources[] = [
				'url' => $url,
				'kind' => self::oneOf($source['kind'] ?? null, self::SOURCE_KINDS, 'other'),
				'searched' => WebSearchResults::find($searchResults, $url) !== null,
			];
		}

		$date = self::stringOrNull($raw['date'] ?? null);
		if ($date !== null && preg_match('/^\d{1,2}\.\d{1,2}$/', $date) !== 1) {
			$date = null;
		}

		return [
			'exists' => self::oneOf($raw['exists'] ?? null, self::EXISTS, 'unclear'),
			'confidence' => self::oneOf($raw['confidence'] ?? null, self::CONFIDENCE, 'low'),
			'date' => $date,
			'date_note' => self::stringOrNull($raw['date_note'] ?? null),
			'origin' => self::stringOrNull($raw['origin'] ?? null),
			'summary' => (string)(self::stringOrNull($raw['summary'] ?? null) ?? ''),
			'sources' => $sources,
		];
	}

	/**
	 * @param list<string> $allowed
	 */
	private static function oneOf(mixed $value, array $allowed, string $default): string
	{
		if (is_string($value) && in_array($value, $allowed, true)) {
			return $value;
		}
		return $default;
	}

	private static function stringOrNull(mixed $value): ?string
	{
		if (!is_string($value)) {
			return null;
		}
		$value = trim($value);
		if ($value === '') {
			return null;
		}
		return $value;
	}

	private function loadPrompt(string $name): string
	{
		$path = $this->promptDir . '/' . $name . '.txt';
		$template = file_get_contents($path);
		if ($template === false) {
			throw new RuntimeException(sprintf('Prompt template not found: %s', $path));
		}
		return $template;
	}
}
