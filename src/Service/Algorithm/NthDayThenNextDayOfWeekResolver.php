<?php

namespace App\Service\Algorithm;

use DateTimeImmutable;
use JetBrains\PhpStorm\ArrayShape;
use Override;

readonly class NthDayThenNextDayOfWeekResolver implements AlgorithmResolverInterface
{
	public function __construct(
		private NthDayOfWeekInMonthResolver $nthDayOfWeekInMonthResolver,
	)
	{
	}

	#[Override]
	#[ArrayShape(['day' => "int", 'month' => "int"])]
	public function calculate(array $args, int $year): array
	{
		$nth = $args['nth'];
		$dayOfWeek = $args['dayOfWeek'];
		$month = $args['month'];
		$afterDayOfWeek = $args['afterDayOfWeek'];

		$base = $this->nthDayOfWeekInMonthResolver->calculate([
			'nth' => $nth,
			'dayOfWeek' => $dayOfWeek,
			'month' => $month,
		], $year);

		$baseDate = new DateTimeImmutable("$year-{$base['month']}-{$base['day']}");
		$nextDate = $baseDate->modify('+1 day');
		$currentDow = (int)$nextDate->format('N');
		$diff = ($afterDayOfWeek - $currentDow + 7) % 7;
		$target = $nextDate->modify("+{$diff} days");

		return [
			'day' => (int)$target->format('j'),
			'month' => (int)$target->format('n')
		];
	}
}
