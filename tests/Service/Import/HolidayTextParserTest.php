<?php

namespace App\Tests\Service\Import;

use App\Service\Import\HolidayTextParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HolidayTextParserTest extends TestCase
{
	private HolidayTextParser $parser;

	protected function setUp(): void
	{
		parent::setUp();
		$this->parser = new HolidayTextParser();
	}

	public static function singleLineProvider(): iterable
	{
		yield 'genitive month' => ['1 stycznia - Dzień Domeny Publicznej', 1, 1, 'Dzień Domeny Publicznej'];
		yield 'month after a dot' => ['14. marca - Dzień Liczby Pi', 14, 3, 'Dzień Liczby Pi'];
		yield 'numeric date' => ['01.01 – Dzień Domeny Publicznej', 1, 1, 'Dzień Domeny Publicznej'];
		yield 'roman month' => ['5 V – Dzień Bez Krawata', 5, 5, 'Dzień Bez Krawata'];
		yield 'name before the date' => ['Dzień Kota – 17 lutego', 17, 2, 'Dzień Kota'];
		yield 'colon separator' => ['22 kwietnia: Dzień Ziemi', 22, 4, 'Dzień Ziemi'];
		yield 'bullet' => ['• 8 marca — Dzień Kobiet', 8, 3, 'Dzień Kobiet'];
		yield 'trailing year is not a date part' => ['1 stycznia 2026 - Nowy Rok', 1, 1, 'Nowy Rok'];
		yield 'description after the name is cut' => [
			'21 marca - Dzień Wagarowicza. Tego dnia uczniowie tradycyjnie uciekają z lekcji.',
			21,
			3,
			'Dzień Wagarowicza',
		];
	}

	#[DataProvider('singleLineProvider')]
	public function testParsesOneLine(string $line, int $day, int $month, string $name): void
	{
		$parsed = $this->parser->parse($line);

		self::assertCount(1, $parsed);
		self::assertTrue($parsed[0]->parsed);
		self::assertSame($day, $parsed[0]->day);
		self::assertSame($month, $parsed[0]->month);
		self::assertSame($name, $parsed[0]->name);
	}

	public static function skippedProvider(): iterable
	{
		yield 'prose with a year' => ['W 2020 roku obchodzono to zupełnie inaczej.'];
		yield 'prose without digits' => ['Poniżej lista najdziwniejszych świąt w roku.'];
		// A recurrence rule is a floating holiday; inventing a fixed date for it would be a guess.
		yield 'recurrence rule' => ['trzeci czwartek listopada – Dzień Bez Zakupów'];
		yield 'empty line' => ['   '];
	}

	#[DataProvider('skippedProvider')]
	public function testSkipsLinesWithNoDateShape(string $line): void
	{
		self::assertSame([], $this->parser->parse($line));
	}

	public static function unparsedProvider(): iterable
	{
		yield 'impossible day' => ['32 stycznia - Święto Nieistniejące'];
		yield 'impossible month' => ['17.30 - Coś O Tej Porze'];
		yield 'february the thirtieth' => ['30 lutego - Święto Nieistniejące'];
		// No sentence break to cut on, so what is left is prose, not a name - reported, not guessed.
		yield 'name too long to trust' => [
			'1 stycznia – Dzień Domeny Publicznej to moment w którym utwory przechodzą do domeny '
			. 'publicznej co oznacza że każdy może z nich korzystać bez opłat i bez ograniczeń',
		];
	}

	#[DataProvider('unparsedProvider')]
	public function testReportsDateShapedLinesItCannotRead(string $line): void
	{
		$parsed = $this->parser->parse($line);

		self::assertCount(1, $parsed);
		self::assertFalse($parsed[0]->parsed);
		self::assertNull($parsed[0]->day);
		self::assertSame(trim($line), $parsed[0]->rawLine);
	}

	public function testMonthHeadingSuppliesTheMonthForBareListItems(): void
	{
		$text = "STYCZEŃ\r\n1. Dzień Domeny Publicznej\r\n2) Dzień Nauki Polskiej\r\nLUTY\r\n17 - Dzień Kota";

		$parsed = $this->parser->parse($text);

		self::assertCount(3, $parsed);
		self::assertSame([1, 1, 'Dzień Domeny Publicznej'], [$parsed[0]->day, $parsed[0]->month, $parsed[0]->name]);
		self::assertSame([2, 1, 'Dzień Nauki Polskiej'], [$parsed[1]->day, $parsed[1]->month, $parsed[1]->name]);
		self::assertSame([17, 2, 'Dzień Kota'], [$parsed[2]->day, $parsed[2]->month, $parsed[2]->name]);
	}

	public static function multipleHolidaysProvider(): iterable
	{
		yield 'comma separated' => [
			'1 stycznia - Dzień Emo, Światowy Dzień Pokoju, Światowy Dzień Kaca',
			1,
			1,
			['Dzień Emo', 'Światowy Dzień Pokoju', 'Światowy Dzień Kaca'],
		];
		// The aside carries both a comma and a date of its own; neither may become a holiday.
		yield 'aside dropped' => [
			'11 stycznia - Dzień Wegetarian (nie mylić z Dniem Wegetarianizmu obchodzonym 1 października), Dzień Lizaka',
			11,
			1,
			['Dzień Wegetarian', 'Dzień Lizaka'],
		];
		yield 'aside only' => [
			'31 stycznia - Międzynarodowy Dzień Przytulania (obchodzony także 24 czerwca)',
			31,
			1,
			['Międzynarodowy Dzień Przytulania'],
		];
		// The source elides the repeated head; the bare tail still matches, because the head is made
		// of the very words HolidayNameMatcher drops as noise.
		yield 'elided head' => [
			'26 stycznia - Światowy Dzień Celnictwa, Transplantologii',
			26,
			1,
			['Światowy Dzień Celnictwa', 'Transplantologii'],
		];
		yield 'conjunction is not a separator' => [
			'25 stycznia - Dzień Sekretarki i Asystentki',
			25,
			1,
			['Dzień Sekretarki i Asystentki'],
		];
	}

	#[DataProvider('multipleHolidaysProvider')]
	public function testSplitsSeveralHolidaysUnderOneDate(string $line, int $day, int $month, array $names): void
	{
		$parsed = $this->parser->parse($line);

		self::assertSame($names, array_map(static fn(object $holiday): string => $holiday->name, $parsed));
		foreach ($parsed as $holiday) {
			self::assertSame([$day, $month], [$holiday->day, $holiday->month]);
		}
	}

	public function testDropsSentenceFragmentsTheCommaSplitCreates(): void
	{
		$line = '1 stycznia - Dzień Domeny Publicznej, czyli moment w którym utwory przechodzą do domeny publicznej';

		$parsed = $this->parser->parse($line);

		self::assertCount(1, $parsed);
		self::assertSame('Dzień Domeny Publicznej', $parsed[0]->name);
	}

	public function testBareListItemsNeedAHeading(): void
	{
		// Without the heading "1." is just a numbered list, and reading it as a day would be a guess.
		self::assertSame([], $this->parser->parse('1. Dzień Domeny Publicznej'));
	}

	public function testKeepsLineNumbersOfTheOriginalText(): void
	{
		$parsed = $this->parser->parse("nagłówek bez daty\r\n\r\n3 maja - Święto Konstytucji");

		self::assertCount(1, $parsed);
		self::assertSame(3, $parsed[0]->lineNumber);
	}
}
