<?php

namespace App\Handler;

use App\DTO\FixedSuggestionDTO;
use App\Entity\FixedHolidaySuggestion;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class FixedHolidaySuggestionHandler implements ReportHandlerInterface
{
	use CountryLookupTrait;

	public function __construct(private EntityManagerInterface $entityManager)
	{
	}

	#[Override]
	public function list(string $userId): array
	{
		return $this->entityManager->getRepository(FixedHolidaySuggestion::class)
			->findBy(['userId' => $userId]);
	}

	#[Override]
	public function create(string $userId, object $payload): void
	{
		assert($payload instanceof FixedSuggestionDTO);
		$report = new FixedHolidaySuggestion($userId, $payload->name, $payload->description, $payload->day, $payload->month, $this->getCountry($payload->country));
		$this->entityManager->persist($report);
		$this->entityManager->flush();
	}

}
