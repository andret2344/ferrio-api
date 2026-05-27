<?php

namespace App\Service\Algorithm;

use DateTimeImmutable;
use JetBrains\PhpStorm\ArrayShape;
use Override;

readonly class EasterOffsetResolver implements AlgorithmResolverInterface
{
	#[Override]
	#[ArrayShape(['day' => "int", 'month' => "int"])]
	public function calculate(array $args, int $year): array
	{
		$offset = $args['offset'];

		$easter = (new DateTimeImmutable("$year-03-21"))->modify('+' . easter_days($year) . ' days');
		$target = $easter->modify(($offset >= 0 ? '+' : '-') . abs($offset) . ' days');

		return [
			'day' => (int)$target->format('j'),
			'month' => (int)$target->format('n'),
		];
	}
}
