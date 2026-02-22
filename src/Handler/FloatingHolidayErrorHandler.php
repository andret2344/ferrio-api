<?php

namespace App\Handler;

use App\DTO\FloatingReportDTO;
use App\Entity\FloatingHolidayError;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\Language;
use App\Entity\ReportType;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class FloatingHolidayErrorHandler implements ReportHandlerInterface
{
	public function __construct(private EntityManagerInterface $entityManager)
	{
	}

	#[Override]
	public function list(string $userId): array
	{
		return $this->entityManager->getRepository(FloatingHolidayError::class)
			->findBy(['userId' => $userId]);
	}

	#[Override]
	public function create(string $userId, object $payload): void
	{
		assert($payload instanceof FloatingReportDTO);
		$language = $this->entityManager->getRepository(Language::class)
			->findOneBy(['code' => $payload->language]);
		/** @var FloatingHolidayMetadata $metadata */
		$metadata = $this->entityManager->getRepository(FloatingHolidayMetadata::class)
			->findOneBy(['id' => $payload->metadata]);
		$reportType = ReportType::from($payload->reportType);
		$report = new FloatingHolidayError($userId, $language, $metadata, $reportType, $payload->description);
		$metadata->reports->add($report);
		$this->entityManager->persist($metadata);
		$this->entityManager->flush();
	}
}
