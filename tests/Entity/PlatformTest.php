<?php

namespace App\Tests\Entity;

use App\Entity\Platform;
use PHPUnit\Framework\TestCase;

class PlatformTest extends TestCase
{
	public function testValuesAreLowercase(): void
	{
		$this->assertSame(['android', 'ios', 'web', 'api', 'unknown'], Platform::values());
	}

	public function testFromInputOrUnknownAcceptsLowercase(): void
	{
		$this->assertSame(Platform::ANDROID, Platform::fromInputOrUnknown('android'));
	}

	public function testFromInputOrUnknownAcceptsUppercase(): void
	{
		$this->assertSame(Platform::ANDROID, Platform::fromInputOrUnknown('ANDROID'));
		$this->assertSame(Platform::IOS, Platform::fromInputOrUnknown('iOS'));
	}

	public function testFromInputOrUnknownFallsBackForGarbage(): void
	{
		$this->assertSame(Platform::UNKNOWN, Platform::fromInputOrUnknown('symbian'));
		$this->assertSame(Platform::UNKNOWN, Platform::fromInputOrUnknown(''));
		$this->assertSame(Platform::UNKNOWN, Platform::fromInputOrUnknown(null));
	}
}
