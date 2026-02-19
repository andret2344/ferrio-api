<?php

namespace App\Service\Algorithm;

use JetBrains\PhpStorm\ArrayShape;
use Override;

readonly class HardcodedDatesResolver implements AlgorithmResolverInterface
{
	#[Override]
	#[ArrayShape(['day' => "int", 'month' => "int"])]
	public function calculate(array $args, int $year): ?array
	{
		$dates = $args['dates'] ?? [];
		$yearStr = (string)$year;

		if (!isset($dates[$yearStr])) {
			return null;
		}

		$parts = explode('.', $dates[$yearStr]);

		return [
			'day' => (int)$parts[0],
			'month' => (int)$parts[1]
		];
	}
}
