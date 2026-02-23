<?php

namespace App\Service\Algorithm;

use DateTimeImmutable;
use JetBrains\PhpStorm\ArrayShape;
use Override;

readonly class FirstDayOfWeekAfterDateResolver implements AlgorithmResolverInterface
{
	#[ArrayShape(['day' => "int", 'month' => "int"])]
	#[Override]
	public function calculate(array $args, int $year): array
	{
		$dayOfWeek = $args['dayOfWeek'];
		$month = $args['month'];
		$day = $args['day'];
		$inclusive = $args['inclusive'] ?? true;

		$date = new DateTimeImmutable("$year-$month-$day");
		if (!$inclusive) {
			$date = $date->modify('+1 day');
		}

		$currentDow = (int)$date->format('N');
		$diff = ($dayOfWeek - $currentDow + 7) % 7;
		$target = $date->modify("+{$diff} days");

		return [
			'day' => (int)$target->format('j'),
			'month' => (int)$target->format('n')
		];
	}
}
