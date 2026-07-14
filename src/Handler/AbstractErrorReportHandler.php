<?php

namespace App\Handler;

use App\DTO\AbstractReportPayload;
use App\Entity\Country;
use App\Entity\Language;
use App\Entity\Platform;
use App\Entity\ReportType;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract readonly class AbstractErrorReportHandler implements ReportHandlerInterface
{
	use RealDeviceResolverTrait;

	public function __construct(
		protected EntityManagerInterface $entityManager,
		#[Target('reports')]
		protected LoggerInterface        $reportsLogger,
	)
	{
	}

	#[Override]
	public function list(string $userId): array
	{
		return $this->entityManager->getRepository($this->getErrorEntityClass())->findBy(['userId' => $userId]);
	}

	#[Override]
	public function create(string $userId, object $payload): void
	{
		$dtoClass = $this->getDtoClass();
		if (!$payload instanceof $dtoClass) {
			throw new InvalidArgumentException(sprintf('Expected %s', $dtoClass));
		}
		/** @var AbstractReportPayload $payload */
		$language = $this->entityManager->getRepository(Language::class)->findOneBy(['code' => $payload->language]);
		if (!$language) {
			$this->reportsLogger->info('Report rejected: language not found', ['user_id' => $userId, 'language' => $payload->language]);
			throw new BadRequestHttpException('Language not found');
		}

		$metadata = $this->entityManager->getRepository($this->getMetadataEntityClass())->findOneBy(['id' => $payload->metadata]);
		if (!$metadata) {
			$this->reportsLogger->info('Report rejected: metadata not found', ['user_id' => $userId, 'metadata' => $payload->metadata]);
			throw new BadRequestHttpException('Metadata not found');
		}

		$device = $payload->device;
		$platform = Platform::fromInputOrUnknown($device?->platform);
		$class = $this->getErrorEntityClass();
		$report = new $class(
			$userId,
			$language,
			$metadata,
			ReportType::from($payload->reportType),
			$payload->description,
			$payload->comment,
			$platform,
			$this->resolveRealDevice($platform, $device?->model),
			Country::normalizeCode($device?->country),
			$device?->osVersion,
			$device?->appVersion,
			$device?->appBuild,
		);
		$metadata->reports->add($report);
		$this->entityManager->persist($metadata);
		$this->entityManager->flush();
	}

	/** @return class-string<AbstractReportPayload> */
	abstract protected function getDtoClass(): string;

	/** @return class-string */
	abstract protected function getErrorEntityClass(): string;

	/** @return class-string */
	abstract protected function getMetadataEntityClass(): string;
}
