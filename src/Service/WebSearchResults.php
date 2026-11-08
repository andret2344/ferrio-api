<?php

namespace App\Service;

/**
 * The pages Anthropic's server-side web search returned in a Messages API response.
 *
 * The search tool (`web_search_20260209`) filters its results through code execution before the
 * model reads them, and an answer written from that filtered output carries no citations. These
 * result blocks are therefore the only record of which pages the search really found - the check
 * that a URL the model writes down is not invented.
 */
final class WebSearchResults
{
	/**
	 * @param list<array<string, mixed>> $content the content blocks of a response
	 * @return array<string, string> URL => page title, in the order the search returned them
	 */
	public static function collect(array $content): array
	{
		$results = [];
		foreach ($content as $block) {
			// A failed search carries an error object as its content, a successful one a list of results.
			$found = $block['content'] ?? null;
			if (($block['type'] ?? null) !== 'web_search_tool_result' || !is_array($found) || !array_is_list($found)) {
				continue;
			}
			foreach ($found as $result) {
				if (!is_array($result)) {
					continue;
				}
				$url = (string)($result['url'] ?? '');
				if (self::isHttpUrl($url) && !isset($results[$url])) {
					$results[$url] = trim((string)($result['title'] ?? ''));
				}
			}
		}
		return $results;
	}

	/**
	 * The result `$url` stands for, as the search spelled it, or null when the search never returned
	 * it. A trailing slash is the one difference tolerated - models add and drop it freely.
	 *
	 * @param array<string, string> $results as returned by collect()
	 */
	public static function find(array $results, string $url): ?string
	{
		if (isset($results[$url])) {
			return $url;
		}
		$bare = rtrim($url, '/');
		foreach (array_keys($results) as $candidate) {
			if (rtrim($candidate, '/') === $bare) {
				return $candidate;
			}
		}
		return null;
	}

	public static function isHttpUrl(string $url): bool
	{
		return preg_match('#^https?://\S+$#i', $url) === 1;
	}
}
