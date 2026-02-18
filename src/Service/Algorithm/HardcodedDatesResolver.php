<?php

namespace App\Service\Algorithm;

use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use Override;

readonly class HardcodedDatesResolver implements AlgorithmResolverInterface
{
	#[Override]
	#[ArrayShape(['day' => "int", 'month' => "int"])]
	public function calculate(array $args, int $year): array
	{
		$dates = $args['dates'] ?? [];
		$yearStr = (string)$year;

		if (!isset($dates[$yearStr])) {
			throw new InvalidArgumentException("No hardcoded date for year $year");
		}

		$parts = explode('.', $dates[$yearStr]);

		return [
			'day' => (int)$parts[0],
			'month' => (int)$parts[1]
		];
	}
}
