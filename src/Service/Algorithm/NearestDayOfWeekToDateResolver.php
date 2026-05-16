<?php

namespace App\Service\Algorithm;

use DateTimeImmutable;
use JetBrains\PhpStorm\ArrayShape;
use Override;

readonly class NearestDayOfWeekToDateResolver implements AlgorithmResolverInterface
{
	#[ArrayShape(['day' => "int", 'month' => "int"])]
	#[Override]
	public function calculate(array $args, int $year): array
	{
		$dayOfWeek = $args['dayOfWeek'];
		$month = $args['month'];
		$day = $args['day'];

		$date = new DateTimeImmutable("$year-$month-$day");
		$currentDow = (int)$date->format('N');

		$forward = ($dayOfWeek - $currentDow + 7) % 7;
		$backward = ($currentDow - $dayOfWeek + 7) % 7;

		$target = $forward <= $backward
			? $date->modify("+{$forward} days")
			: $date->modify("-{$backward} days");

		return [
			'day' => (int)$target->format('j'),
			'month' => (int)$target->format('n')
		];
	}
}
