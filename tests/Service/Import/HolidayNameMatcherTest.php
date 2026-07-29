<?php

namespace App\Tests\Service\Import;

use App\Service\Import\HolidayNameMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HolidayNameMatcherTest extends TestCase
{
	public static function normalizeProvider(): iterable
	{
		yield 'diacritics folded' => ['Światowy Dzień Kota', 'swiatowy dzien kota'];
		yield 'punctuation becomes space' => ['Dzień Matki - Polska!', 'dzien matki polska'];
		yield 'whitespace collapsed' => ["  Dzień   Ojca \t", 'dzien ojca'];
		yield 'digits kept' => ['Dzień 1 Maja', 'dzien 1 maja'];
	}

	#[DataProvider('normalizeProvider')]
	public function testNormalize(string $input, string $expected): void
	{
		self::assertSame($expected, HolidayNameMatcher::normalize($input));
	}

	public static function tokensProvider(): iterable
	{
		yield 'noise words dropped' => ['Międzynarodowy Dzień Liczby Pi', ['liczb']];
		yield 'inflection stemmed to the same form' => ['Święto Kotów', ['kot']];
		yield 'short tokens dropped' => ['Dzień Pi i Matematyki', ['matematyk']];
		// Nothing but noise words: keeping them beats returning an unmatchable empty set.
		yield 'all-noise name keeps its words' => ['Światowy Dzień', ['swiatow', 'dzien']];
	}

	#[DataProvider('tokensProvider')]
	public function testTokens(string $input, array $expected): void
	{
		self::assertSame($expected, HolidayNameMatcher::tokens($input));
	}

	public static function scoreProvider(): iterable
	{
		yield 'identical' => ['Dzień Kota', 'Dzień Kota', 1.0];
		yield 'noise prefix ignored' => ['Światowy Dzień Kota', 'Dzień Kota', 1.0];
		yield 'declension ignored' => ['Dzień Kota', 'Święto Kotów', 1.0];
		yield 'unrelated' => ['Dzień Kota', 'Dzień Ziemniaka', 0.0];
		yield 'empty name' => ['', 'Dzień Kota', 0.0];
		// Same declension pattern, so the stems end alike ("kierowc" / "wiezowc") and are two edits
		// apart. They diverge at the first letter, which inflection never does.
		yield 'shared ending is not a match' => ['Dzień Kierowców Ciężarówek', 'Dzień Wieżowca', 0.0];
		yield 'shared ending, longer stems' => ['Dzień Pracownika', 'Dzień Ratownika', 0.0];
	}

	#[DataProvider('scoreProvider')]
	public function testScore(string $left, string $right, float $expected): void
	{
		self::assertEqualsWithDelta($expected, HolidayNameMatcher::score($left, $right), 0.001);
	}

	public function testExtraWordLandsBetweenTheThresholds(): void
	{
		// The whole point of the AMBIGUOUS bucket: close enough to be worth a look, not close enough
		// to act on unseen.
		$score = HolidayNameMatcher::score('Dzień Liczby Pi', 'Dzień Liczby Pi i Matematyki');
		self::assertGreaterThanOrEqual(HolidayNameMatcher::CANDIDATE_THRESHOLD, $score);
		self::assertLessThan(HolidayNameMatcher::MATCH_THRESHOLD, $score);
	}

	public function testScoreIsSymmetric(): void
	{
		self::assertSame(
			HolidayNameMatcher::score('Światowy Dzień Zwierząt', 'Dzień Zwierzat Domowych'),
			HolidayNameMatcher::score('Dzień Zwierzat Domowych', 'Światowy Dzień Zwierząt')
		);
	}
}
