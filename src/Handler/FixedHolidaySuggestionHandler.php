<?php

namespace App\Handler;

use App\DTO\FixedSuggestionDTO;
use App\Entity\FixedHolidaySuggestion;
use App\Entity\Platform;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Override;

readonly class FixedHolidaySuggestionHandler implements ReportHandlerInterface
{
	use CountryLookupTrait;
	use RealDeviceResolverTrait;

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
		if (!$payload instanceof FixedSuggestionDTO) {
			throw new InvalidArgumentException('Expected FixedSuggestionDTO');
		}
		$device = $payload->device;
		$platform = Platform::fromInputOrUnknown($device?->platform);
		$countries = $this->getCountriesByCodes($payload->country, $device?->country);
		$report = new FixedHolidaySuggestion(
			$userId,
			$payload->name,
			$payload->description,
			$payload->day,
			$payload->month,
			$this->pickCountry($countries, $payload->country),
			comment      : $payload->comment,
			platform     : $platform,
			realDevice   : $this->resolveRealDevice($platform, $device?->model),
			deviceCountry: $this->pickCountry($countries, $device?->country),
			osVersion    : $device?->osVersion,
			appVersion   : $device?->appVersion,
		);
		$this->entityManager->persist($report);
		$this->entityManager->flush();
	}
}
