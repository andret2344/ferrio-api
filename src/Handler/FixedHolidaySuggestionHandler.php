<?php

namespace App\Handler;

use App\Entity\FixedHolidaySuggestion;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class FixedHolidaySuggestionHandler implements ReportHandlerInterface
{
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
	public function create(string $userId, array $payload): void
	{
		$name = $payload['name'] ?? null;
		$day = $payload['day'] ?? null;
		$month = $payload['month'] ?? null;
		$description = $payload['description'] ?? null;
		$report = new FixedHolidaySuggestion($userId, $name, $description, $day, $month, new DateTimeImmutable());
		$this->entityManager->persist($report);
		$this->entityManager->flush();
	}
}
