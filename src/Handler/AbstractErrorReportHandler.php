<?php

namespace App\Handler;

use App\Entity\Language;
use App\Entity\ReportType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract readonly class AbstractErrorReportHandler implements ReportHandlerInterface
{
	public function __construct(protected EntityManagerInterface $entityManager)
	{
	}

	#[Override]
	public function list(string $userId): array
	{
		return $this->entityManager->getRepository($this->getErrorEntityClass())
			->findBy(['userId' => $userId]);
	}

	#[Override]
	public function create(string $userId, object $payload): void
	{
		$dto = $this->validatePayload($payload);
		$language = $this->entityManager->getRepository(Language::class)
			->findOneBy(['code' => $dto['language']]);
		if (!$language) {
			throw new BadRequestHttpException('Language not found');
		}

		$metadata = $this->entityManager->getRepository($this->getMetadataEntityClass())
			->findOneBy(['id' => $dto['metadata']]);
		if (!$metadata) {
			throw new BadRequestHttpException('Metadata not found');
		}

		$reportType = ReportType::from($dto['reportType']);
		$report = $this->createErrorEntity($userId, $language, $metadata, $reportType, $dto['description'], $dto['comment']);
		$metadata->reports->add($report);
		$this->entityManager->persist($metadata);
		$this->entityManager->flush();
	}

	/**
	 * @return array{language: string, metadata: int, reportType: string, description: ?string, comment: ?string}
	 */
	abstract protected function validatePayload(object $payload): array;

	/** @return class-string */
	abstract protected function getErrorEntityClass(): string;

	/** @return class-string */
	abstract protected function getMetadataEntityClass(): string;

	abstract protected function createErrorEntity(string $userId, Language $language, object $metadata, ReportType $reportType, ?string $description, ?string $comment): object;
}
