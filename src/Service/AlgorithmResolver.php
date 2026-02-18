<?php

namespace App\Service;

use App\Enum\Algorithm;
use App\Service\Algorithm\AlgorithmResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

readonly class AlgorithmResolver
{
	public function __construct(
		#[AutowireLocator(AlgorithmResolverInterface::class, defaultIndexMethod: '')]
		private ServiceLocator $resolvers,
	)
	{
	}

	/**
	 * @param array<string, mixed> $args
	 *
	 * @return array{day: int, month: int}
	 */
	public function resolve(Algorithm $algorithm, array $args, int $year): array
	{
		return $this->resolvers->get($algorithm->resolverClass())
			->calculate($args, $year);
	}
}
