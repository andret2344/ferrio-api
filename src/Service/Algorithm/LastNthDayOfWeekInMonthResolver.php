<?php

namespace App\Service\Algorithm;

use DateTimeImmutable;
use JetBrains\PhpStorm\ArrayShape;
use Override;

readonly class LastNthDayOfWeekInMonthResolver implements AlgorithmResolverInterface
{
	#[Override]
	#[ArrayShape(['day' => "int", 'month' => "int"])]
	public function calculate(array $args, int $year): array
	{
		$nth = $args['nth'];
		$dayOfWeek = $args['dayOfWeek'];
		$month = $args['month'];

		$date = new DateTimeImmutable("$year-$month-01");
		$lastDay = (int)$date->format('t');
		$lastDate = new DateTimeImmutable("$year-$month-$lastDay");
		$currentDow = (int)$lastDate->format('N');

		$diff = ($currentDow - $dayOfWeek + 7) % 7;
		$lastOccurrence = $lastDate->modify("-{$diff} days");
		$target = $lastOccurrence->modify('-' . ($nth - 1) . ' weeks');

		return [
			'day' => (int)$target->format('j'),
			'month' => $month
		];
	}
}
