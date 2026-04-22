<?php

namespace App\Service\Algorithm;

use JetBrains\PhpStorm\ArrayShape;
use Override;

readonly class FixedDateWithChangesResolver implements AlgorithmResolverInterface
{
	#[Override]
	#[ArrayShape(['day' => "int", 'month' => "int"])]
	public function calculate(array $args, int $year): array
	{
		$day = $args['defaultDay'];
		$month = $args['defaultMonth'];

		foreach ($args['changes'] as $change) {
			if ($year >= $change['fromYear']) {
				$day = $change['day'];
				$month = $change['month'];
			}
		}

		return [
			'day' => $day,
			'month' => $month,
		];
	}
}
