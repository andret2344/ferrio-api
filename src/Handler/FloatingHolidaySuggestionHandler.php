<?php

namespace App\Handler;

use App\DTO\FloatingSuggestionDTO;
use App\Entity\Country;
use App\Entity\FloatingHolidaySuggestion;
use App\Entity\Platform;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Override;

readonly class FloatingHolidaySuggestionHandler implements ReportHandlerInterface
{
	use CountryLookupTrait;
	use RealDeviceResolverTrait;

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
		if (!$payload instanceof FloatingSuggestionDTO) {
			throw new InvalidArgumentException('Expected FloatingSuggestionDTO');
		}
		$device = $payload->device;
		$platform = Platform::fromInputOrUnknown($device?->platform);
		$report = new FloatingHolidaySuggestion(
			$userId,
			$payload->name,
			$payload->description,
			$payload->date,
			$this->getCountry($payload->country),
			comment      : $payload->comment,
			platform     : $platform,
			realDevice   : $this->resolveRealDevice($platform, $device?->model),
			deviceCountry: Country::normalizeCode($device?->country),
			osVersion    : $device?->osVersion,
			appVersion   : $device?->appVersion,
			appBuild     : $device?->appBuild,
		);
		$this->entityManager->persist($report);
		$this->entityManager->flush();
	}
}
