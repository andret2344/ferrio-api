<?php

namespace App\Handler;

use App\Entity\Country;
use App\Entity\FloatingHolidaySuggestion;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class FloatingHolidaySuggestionHandler implements ReportHandlerInterface
{
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
	public function create(string $userId, array $payload): void
	{
		$name = $payload['name'] ?? null;
		$date = $payload['date'] ?? null;
		$description = $payload['description'] ?? null;
		$report = new FloatingHolidaySuggestion($userId, $name, $description, $date, $this->getCountry($payload['country']));
		$this->entityManager->persist($report);
		$this->entityManager->flush();
	}

	public function getCountry(?string $country): ?Country
	{
		if ($country === null) {
			return null;
		}
		$repo = $this->entityManager->getRepository(Country::class);
		return $repo->findOneBy(['isoCode' => $country]);
	}
}
