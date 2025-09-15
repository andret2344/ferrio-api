<?php

namespace App\Handler;

use App\Entity\FixedHolidayError;
use App\Entity\FixedHolidayMetadata;
use App\Entity\Language;
use App\Entity\ReportType;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class FixedHolidayErrorHandler implements ReportHandlerInterface
{
	public function __construct(private EntityManagerInterface $entityManager)
	{
	}

	#[Override]
	public function list(string $userId): array
	{
		return $this->entityManager->getRepository(FixedHolidayError::class)
			->findBy(['userId' => $userId]);
	}

	#[Override]
	public function create(string $userId, array $payload): void
	{
		$language = $this->entityManager->getRepository(Language::class)
			->findOneBy(['code' => $payload['language']]);
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->entityManager->getRepository(FixedHolidayMetadata::class)
			->findOneBy(['id' => $payload['metadata']]);
		$reportType = ReportType::from($payload['report_type']);
		$description = $payload['description'] ?? null;
		$report = new FixedHolidayError($userId, $language, $metadata, $reportType, $description);
		$metadata->addReport($report);
		$this->entityManager->persist($metadata);
		$this->entityManager->flush();
	}
}
