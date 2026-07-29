<?php

namespace App\Service\Import;

/**
 * Fuzzy comparison of Polish holiday names. Pure and static - it holds no state and touches no
 * database, so it can be unit-tested on plain strings.
 *
 * Why not SQL: MySQL's only built-in fuzzy operator is SOUNDEX, which encodes English phonetics and
 * is worthless for Polish. The stored set is a few thousand names, so it is loaded once and compared
 * in PHP instead.
 *
 * Three steps, each answering a way the same holiday gets written differently across sources:
 *
 *  1. normalize() - lowercase and fold Polish diacritics, so "Święto" and "Swieto" are one word.
 *  2. stem() - strip the inflectional ending. Polish declines every noun, and sources disagree on
 *     the case ("Dzień Kota" vs "Święto Kotów"), which plain edit distance does not survive:
 *     kota -> kotow is two edits over a four-letter word, indistinguishable from an unrelated pair.
 *  3. noise removal - drop the words that appear in a large share of holiday names ("Światowy",
 *     "Dzień", "Święto"). Without this every holiday scores highly against every other one, since
 *     almost all of them are called "<adjective> Dzień <something>".
 *
 * The score is the Dice coefficient over the surviving stems, paired greedily with a small edit
 * tolerance for the spelling differences stemming does not cover.
 */
class HolidayNameMatcher
{
	/** At or above this score the names are treated as the same holiday. */
	public const float MATCH_THRESHOLD = 0.85;

	/** Between this and MATCH_THRESHOLD the pair is reported for a human decision. */
	public const float CANDIDATE_THRESHOLD = 0.5;

	private const array DIACRITICS = [
		'ą' => 'a',
		'ć' => 'c',
		'ę' => 'e',
		'ł' => 'l',
		'ń' => 'n',
		'ó' => 'o',
		'ś' => 's',
		'ź' => 'z',
		'ż' => 'z',
	];

	/** Inflectional endings, longest first; only one is stripped per token. */
	private const array ENDINGS = ['owego', 'iego', 'ego', 'ach', 'ami', 'owi', 'ow', 'ie', 'ia', 'em', 'a', 'e', 'i', 'o', 'u', 'y'];

	/** A stem never drops below this, or unrelated short words collapse onto each other. */
	private const int MIN_STEM_LENGTH = 3;

	/**
	 * Words carrying no signal, listed as the stems stem() produces, so every inflection of them is
	 * covered by one entry.
	 */
	private const array NOISE_STEMS = [
		'swiatow',
		'miedzynarodow',
		'ogolnopolsk',
		'narodow',
		'europejsk',
		'powszechn',
		'globaln',
		'dzien',
		'dni',
		'swiet',
		'obchod',
	];

	/** Shorter tokens than this are prepositions and initials, not content. */
	private const int MIN_TOKEN_LENGTH = 3;

	/** Below this length a stem must match exactly; edit distance on short words is noise. */
	private const int MIN_FUZZY_LENGTH = 4;

	/**
	 * From this length on, two edits are tolerated instead of one. Set high on purpose: two edits
	 * over a seven-letter stem is a 29% difference, which paired "kierowc" with "wiezowc".
	 */
	private const int TWO_EDIT_LENGTH = 10;

	/** Polish inflection never touches the start of a word, so a fuzzy pair must agree on it. */
	private const int MIN_COMMON_PREFIX = 2;

	public static function normalize(string $name): string
	{
		$folded = strtr(mb_strtolower($name, 'UTF-8'), self::DIACRITICS);
		$plain = (string)preg_replace('/[^a-z0-9]+/', ' ', $folded);
		return trim((string)preg_replace('/\s+/', ' ', $plain));
	}

	public static function stem(string $token): string
	{
		foreach (self::ENDINGS as $ending) {
			if (str_ends_with($token, $ending) && strlen($token) - strlen($ending) >= self::MIN_STEM_LENGTH) {
				return substr($token, 0, -strlen($ending));
			}
		}
		return $token;
	}

	/**
	 * @return string[] the meaningful stems, or every stem when dropping the noise words would leave
	 *                  nothing (a holiday genuinely called just "Dzień Dziecka" must stay matchable)
	 */
	public static function tokens(string $name): array
	{
		$normalized = self::normalize($name);
		if ($normalized === '') {
			return [];
		}
		$all = explode(' ', $normalized);
		$core = [];
		foreach ($all as $token) {
			if (strlen($token) < self::MIN_TOKEN_LENGTH) {
				continue;
			}
			$stem = self::stem($token);
			if (in_array($stem, self::NOISE_STEMS, true)) {
				continue;
			}
			$core[] = $stem;
		}
		if ($core === []) {
			return array_map(self::stem(...), $all);
		}
		return $core;
	}

	public static function score(string $left, string $right): float
	{
		return self::scoreTokens(self::tokens($left), self::tokens($right));
	}

	/**
	 * @param string[] $left
	 * @param string[] $right
	 * @return float 0.0 (nothing in common) to 1.0 (every stem paired up)
	 */
	public static function scoreTokens(array $left, array $right): float
	{
		if ($left === [] || $right === []) {
			return 0.0;
		}
		// Greedy pairing: each left stem consumes at most one right stem, so a name that repeats a
		// word cannot inflate its own score.
		$pool = $right;
		$paired = 0;
		foreach ($left as $token) {
			foreach ($pool as $index => $other) {
				if (self::stemsMatch($token, $other)) {
					$paired++;
					unset($pool[$index]);
					break;
				}
			}
		}
		return 2.0 * $paired / (count($left) + count($right));
	}

	private static function stemsMatch(string $left, string $right): bool
	{
		if ($left === $right) {
			return true;
		}
		$shortest = min(strlen($left), strlen($right));
		if ($shortest < self::MIN_FUZZY_LENGTH) {
			return false;
		}
		// Two stems that diverge immediately are different words that happen to end alike - Polish is
		// full of them, since the shared ending is what the declension pattern dictates.
		if (substr($left, 0, self::MIN_COMMON_PREFIX) !== substr($right, 0, self::MIN_COMMON_PREFIX)) {
			return false;
		}
		if ($shortest >= self::TWO_EDIT_LENGTH) {
			$allowed = 2;
		} else {
			$allowed = 1;
		}
		return levenshtein($left, $right) <= $allowed;
	}
}
