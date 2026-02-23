<?php

namespace App\Handler;

use App\DTO\FloatingSuggestionDTO;
use App\Entity\FloatingHolidaySuggestion;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class FloatingHolidaySuggestionHandler implements ReportHandlerInterface
{
	use CountryLookupTrait;

	public function __construct(private EntityManagerInterface $entityManager)
	{
	}

	#[Override]
	public function list(string $userId): array
	{
		return $this->entityManager->getRepository(FloatingHolidaySuggestion::class)
			->findBy(['userId' => $userId]);
	}

	#[Override]
	public function create(string $userId, object $payload): void
	{
		assert($payload instanceof FloatingSuggestionDTO);
		$report = new FloatingHolidaySuggestion($userId, $payload->name, $payload->description, $payload->date, $this->getCountry($payload->country));
		$this->entityManager->persist($report);
		$this->entityManager->flush();
	}

}
