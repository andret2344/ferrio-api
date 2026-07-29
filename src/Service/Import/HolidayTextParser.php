<?php

namespace App\Service\Import;

use App\DTO\ParsedHoliday;

/**
 * Turns text pasted from a blog or forum into (day, month, name) triples.
 *
 * Three deliberate rules, because the input is other people's prose and the cost of being wrong is
 * asymmetric - a bad row silently becomes a bad holiday, a skipped row costs one manual check:
 *
 *  1. A line that reads as a date but fails validation is returned *unparsed* (day and month null),
 *     never repaired by guessing.
 *  2. A line with no date shape at all is dropped silently. Blog prose is mostly such lines, and
 *     reporting them would bury the rows that matter.
 *  3. Recurrence phrases ("trzeci czwartek listopada") carry no date shape, so rule 2 drops them.
 *     They are floating holidays and this parser deliberately does not invent a fixed date for them.
 *
 * A line that is nothing but a month name is read as a heading and becomes the month context for the
 * bare-day list items that follow it ("1. Dzień Domeny Publicznej" under a "STYCZEŃ" heading).
 *
 * One line can yield several holidays: sources routinely list them comma-separated under one date
 * ("1 stycznia - Dzień Emo, Światowy Dzień Pokoju, Światowy Dzień Kaca"). Parenthesised asides are
 * removed before the split, since they carry commas and dates of their own ("obchodzony także 24
 * czerwca") that would otherwise become holidays in their own right.
 */
class HolidayTextParser
{
	/**
	 * Regex alternation over Polish month names, nominative and genitive, with and without
	 * diacritics. Longest forms first so "listopada" is never truncated to "listopad".
	 */
	private const string MONTHS = 'pa[źz]dziernika|pa[źz]dziernik|wrze[śs]nia|wrzesie[nń]|kwietnia|kwiecie[nń]'
		. '|listopada|listopad|sierpnia|sierpie[nń]|grudnia|grudzie[nń]|stycznia|stycze[nń]'
		. '|czerwca|czerwiec|lutego|marca|marzec|lipca|lipiec|luty|maja|maj';

	/** Keyed by the month word as HolidayNameMatcher::normalize() renders it. */
	private const array MONTH_BY_WORD = [
		'stycznia' => 1,
		'styczen' => 1,
		'lutego' => 2,
		'luty' => 2,
		'marca' => 3,
		'marzec' => 3,
		'kwietnia' => 4,
		'kwiecien' => 4,
		'maja' => 5,
		'maj' => 5,
		'czerwca' => 6,
		'czerwiec' => 6,
		'lipca' => 7,
		'lipiec' => 7,
		'sierpnia' => 8,
		'sierpien' => 8,
		'wrzesnia' => 9,
		'wrzesien' => 9,
		'pazdziernika' => 10,
		'pazdziernik' => 10,
		'listopada' => 11,
		'listopad' => 11,
		'grudnia' => 12,
		'grudzien' => 12,
	];

	/**
	 * Roman month numerals are matched case-sensitively on purpose: lowercase "x" and "i" are common
	 * Polish words and abbreviations, and accepting them turns "3 x dziennie" into a date.
	 */
	private const string ROMAN = 'XII|XI|IX|VIII|VII|VI|IV|III|II|X|V|I';

	/** An optional year trailing the date, so "1 stycznia 2026 r." leaves nothing behind in the name. */
	private const string YEAR = '(?:\s+\d{4}(?:\s*r\.?)?)?';

	private const array ROMAN_MONTHS = [
		'I' => 1,
		'II' => 2,
		'III' => 3,
		'IV' => 4,
		'V' => 5,
		'VI' => 6,
		'VII' => 7,
		'VIII' => 8,
		'IX' => 9,
		'X' => 10,
		'XI' => 11,
		'XII' => 12,
	];

	private const array DAYS_IN_MONTH = [1 => 31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

	/**
	 * Words that appear in prose and never in a holiday name, folded the way
	 * HolidayNameMatcher::normalize() renders them. Kept deliberately short - a word that could
	 * plausibly sit in a real holiday name does not belong here.
	 */
	private const array PROSE_WORDS = [
		'ze',
		'ktory',
		'ktora',
		'ktore',
		'ktorym',
		'ktorej',
		'ktorych',
		'poniewaz',
		'dlatego',
		'jednak',
		'bowiem',
		'czyli',
		'oznacza',
		'mylic',
		'obchodzony',
		'obchodzona',
		'obchodzone',
	];

	private const int MIN_NAME_LENGTH = 3;

	/** Past this, what was captured is prose about the holiday rather than its name. */
	private const int MAX_NAME_LENGTH = 120;

	/**
	 * @return ParsedHoliday[] in the order the lines appeared
	 */
	public function parse(string $text): array
	{
		$lines = preg_split('/\R/u', $text);
		if ($lines === false) {
			return [];
		}
		$holidays = [];
		$contextMonth = null;
		foreach ($lines as $index => $line) {
			$number = $index + 1;
			$raw = trim($line);
			if ($raw === '') {
				continue;
			}
			$heading = $this->headingMonth($raw);
			if ($heading !== null) {
				$contextMonth = $heading;
				continue;
			}
			foreach ($this->parseLine($number, $raw, $contextMonth) as $holiday) {
				$holidays[] = $holiday;
			}
		}
		return $holidays;
	}

	/**
	 * @return ParsedHoliday[] one per holiday on the line - sources routinely put several under one
	 *                         date - or a single unparsed entry carrying the whole line
	 */
	private function parseLine(int $number, string $raw, ?int $contextMonth): array
	{
		$date = $this->extractDate($raw, $contextMonth);
		if ($date === null) {
			if ($this->looksLikeDate($raw)) {
				return [new ParsedHoliday($number, $raw, $raw)];
			}
			return [];
		}
		[$day, $month, $offset, $length] = $date;
		if (!$this->isValidDate($day, $month)) {
			return [new ParsedHoliday($number, $raw, $raw)];
		}
		$names = $this->extractNames($raw, $offset, $length);
		if ($names === []) {
			return [new ParsedHoliday($number, $raw, $raw)];
		}
		$holidays = [];
		foreach ($names as $name) {
			$holidays[] = new ParsedHoliday($number, $raw, $name, $day, $month);
		}
		return $holidays;
	}

	/**
	 * @return array{int, int, int, int}|null day, month, and the byte offset/length of the date text
	 */
	private function extractDate(string $raw, ?int $contextMonth): ?array
	{
		// "1 stycznia", "14. marca", "1 stycznia 2026 r." - a trailing year is swallowed by the match
		// so it does not survive into the name.
		if (preg_match('/\b(\d{1,2})\s*\.?\s*(' . self::MONTHS . ')\b' . self::YEAR . '/ui', $raw, $matches, PREG_OFFSET_CAPTURE) === 1) {
			$word = HolidayNameMatcher::normalize($matches[2][0]);
			return [(int)$matches[1][0], self::MONTH_BY_WORD[$word], $matches[0][1], strlen($matches[0][0])];
		}
		// "1 I", "12 VIII" - not followed by another number, so "1 i 2 stycznia" is left alone.
		if (preg_match('/\b(\d{1,2})\s+(' . self::ROMAN . ')\b(?!\s*\d)' . self::YEAR . '/u', $raw, $matches, PREG_OFFSET_CAPTURE) === 1) {
			return [(int)$matches[1][0], self::ROMAN_MONTHS[$matches[2][0]], $matches[0][1], strlen($matches[0][0])];
		}
		// "01.01", "01.01.2026". A fourth number would mean this is not a date at all, so the
		// lookahead drops it rather than reading the first two components anyway.
		if (preg_match('/\b(\d{1,2})\s*[.\-\/]\s*(\d{1,2})(?:\s*[.\-\/]\s*\d{2,4})?\b(?!\s*[.\-\/]\s*\d)/u', $raw, $matches, PREG_OFFSET_CAPTURE) === 1) {
			return [(int)$matches[1][0], (int)$matches[2][0], $matches[0][1], strlen($matches[0][0])];
		}
		// A bare list item under a month heading. Anchored to the start of the line on purpose:
		// accepting a bare number anywhere would read "od 5 lat" as the fifth of the month.
		if ($contextMonth !== null
			&& preg_match('/^[\s\p{Pd}•*·>»]*(\d{1,2})\s*[.)\-:]\s*/u', $raw, $matches, PREG_OFFSET_CAPTURE) === 1) {
			return [(int)$matches[1][0], $contextMonth, $matches[0][1], strlen($matches[0][0])];
		}
		return null;
	}

	private function looksLikeDate(string $raw): bool
	{
		if (preg_match('/\b\d{1,2}\s*[.\-\/]\s*\d{1,2}\b/u', $raw) === 1) {
			return true;
		}
		if (preg_match('/\b\d{1,3}\s*\.?\s*(' . self::MONTHS . ')\b/ui', $raw) === 1) {
			return true;
		}
		return preg_match('/\b\d{1,3}\s+(' . self::ROMAN . ')\b/u', $raw) === 1;
	}

	private function isValidDate(int $day, int $month): bool
	{
		if ($month < 1 || $month > 12) {
			return false;
		}
		return $day >= 1 && $day <= self::DAYS_IN_MONTH[$month];
	}

	/**
	 * @return string[] every holiday name on the line, in order; empty when none survived
	 */
	private function extractNames(string $raw, int $offset, int $length): array
	{
		$withoutDate = substr_replace($raw, ' ', $offset, $length);
		// Asides carry their own dates and their own commas ("obchodzony także 24 czerwca"), so they
		// go before both the sentence cut and the split.
		$withoutAsides = (string)preg_replace('/\([^)]*\)|\[[^\]]*\]/u', ' ', $withoutDate);
		// Blog entries run the description straight on from the name; keep the first sentence only.
		$firstSentence = preg_split('/(?<=[.!?])\s+(?=\p{Lu})/u', $withoutAsides, 2);
		if ($firstSentence === false) {
			return [];
		}
		$segments = preg_split('/[,;]/u', $firstSentence[0]);
		if ($segments === false) {
			return [];
		}
		$names = [];
		foreach ($segments as $segment) {
			$name = $this->cleanName($segment);
			if ($this->isName($name)) {
				$names[] = $name;
			}
		}
		return $names;
	}

	/**
	 * Splitting on commas is what makes "1 stycznia - Dzień Emo, Dzień Kaca" two holidays, but the
	 * same split also chops a prose sentence into fragments. These three checks throw the fragments
	 * away: a holiday name is capitalised, short, and does not read like a clause.
	 */
	private function isName(string $name): bool
	{
		if (mb_strlen($name) < self::MIN_NAME_LENGTH || mb_strlen($name) > self::MAX_NAME_LENGTH) {
			return false;
		}
		if (preg_match('/^\p{Lu}/u', $name) !== 1) {
			return false;
		}
		foreach (explode(' ', HolidayNameMatcher::normalize($name)) as $word) {
			if (in_array($word, self::PROSE_WORDS, true)) {
				return false;
			}
		}
		return true;
	}

	private function cleanName(string $text): string
	{
		$collapsed = (string)preg_replace('/\s+/u', ' ', $text);
		$trimmed = (string)preg_replace('/^[\s\p{P}\p{S}]+|[\s\p{P}\p{S}]+$/u', '', $collapsed);
		return trim($trimmed);
	}

	private function headingMonth(string $raw): ?int
	{
		if (preg_match('/^[^\p{L}\d]*(' . self::MONTHS . ')[^\p{L}\d]*$/ui', $raw, $matches) !== 1) {
			return null;
		}
		return self::MONTH_BY_WORD[HolidayNameMatcher::normalize($matches[1])];
	}
}
