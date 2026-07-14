<?php

namespace App\Tests\Service;

use App\Service\ApiHitCounter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ApiHitCounterTest extends TestCase
{
	public static function countableProvider(): iterable
	{
		yield 'v2 endpoint' => ['GET', '/v2/holiday/en/day/3/1', true];
		yield 'v3 endpoint' => ['GET', '/v3/holidays', true];
		yield 'bare version' => ['GET', '/v3', true];
		// A 404 under a known version is still real client traffic — worth seeing on the stats page.
		yield 'unknown route under a known version' => ['GET', '/v3/typo', true];

		yield 'scanner probing a php file' => ['GET', '/v543.php', false];
		yield 'scanner probing php under a known version' => ['GET', '/v2/config.php', false];
		yield 'unknown version' => ['GET', '/v543/holidays', false];
		yield 'version-like prefix' => ['GET', '/v3x/holidays', false];
		yield 'admin UI' => ['GET', '/admin/stats', false];
		yield 'asset' => ['GET', '/build/app.js', false];
		yield 'root' => ['GET', '/', false];
		yield 'write to a counted path' => ['POST', '/v3/users/reports', false];
	}

	#[DataProvider('countableProvider')]
	public function testIsCountable(string $method, string $path, bool $expected): void
	{
		self::assertSame($expected, ApiHitCounter::isCountable($method, $path));
	}
}
