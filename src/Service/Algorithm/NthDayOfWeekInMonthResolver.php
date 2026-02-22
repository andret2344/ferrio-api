<?php

namespace App\Service\Algorithm;

use DateTimeImmutable;
use JetBrains\PhpStorm\ArrayShape;
use Override;

readonly class NthDayOfWeekInMonthResolver implements AlgorithmResolverInterface
{
	#[Override]
	#[ArrayShape(['day' => "int", 'month' => "int"])]
	public function calculate(array $args, int $year): array
	{
		$nth = $args['nth'];
		$dayOfWeek = $args['dayOfWeek'];
		$month = $args['month'];

		$date = new DateTimeImmutable("$year-$month-01");
		$currentDow = (int)$date->format('N');

		$diff = ($dayOfWeek - $currentDow + 7) % 7;
		$firstOccurrence = $date->modify("+{$diff} days");
		$target = $firstOccurrence->modify('+' . ($nth - 1) . ' weeks');

		return [
			'day' => (int)$target->format('j'),
			'month' => $month
		];
	}
}
