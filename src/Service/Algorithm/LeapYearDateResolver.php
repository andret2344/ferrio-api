<?php

namespace App\Service\Algorithm;

use DateTimeImmutable;
use JetBrains\PhpStorm\ArrayShape;
use Override;

readonly class LeapYearDateResolver implements AlgorithmResolverInterface
{
	#[Override]
	#[ArrayShape(['day' => "int", 'month' => "int"])]
	public function calculate(array $args, int $year): array
	{
		$isLeap = new DateTimeImmutable("$year-01-01")->format('L') === '1';

		if ($isLeap) {
			return [
				'day' => $args['leapDay'],
				'month' => $args['leapMonth']
			];
		}

		return [
			'day' => $args['nonLeapDay'],
			'month' => $args['nonLeapMonth']
		];
	}
}
