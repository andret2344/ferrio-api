<?php

namespace App\Service\Algorithm;

use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface AlgorithmResolverInterface
{
	/**
	 * @param array<string, mixed> $args
	 *
	 * @return array{day: int, month: int}|null
	 */
	#[ArrayShape(['day' => "int", 'month' => "int"])]
	public function calculate(array $args, int $year): ?array;
}
