<?php

namespace App\Service\Algorithm;

use DateTimeImmutable;
use JetBrains\PhpStorm\ArrayShape;
use Override;

readonly class EarthHourResolver implements AlgorithmResolverInterface
{
	#[Override]
	#[ArrayShape(['day' => "int", 'month' => "int"])]
	public function calculate(array $args, int $year): array
	{
		$lastSatMarch = new DateTimeImmutable("last saturday of march $year");

		$base = new DateTimeImmutable("$year-03-21");
		$easter = $base->modify('+' . easter_days($year) . ' days');
		$holySaturday = $easter->modify('-1 day');

		if ($lastSatMarch->format('Y-m-d') === $holySaturday->format('Y-m-d')) {
			$lastSatMarch = $lastSatMarch->modify('-7 days');
		}

		return [
			'day' => (int)$lastSatMarch->format('j'),
			'month' => (int)$lastSatMarch->format('n'),
		];
	}
}
