<?php

namespace App\Service;

use App\Entity\FloatingHoliday;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\HolidayDay;
use App\Repository\FixedHolidayRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class HolidayServiceV3
{
	public function __construct(private FixedHolidayRepository $fixedHolidayRepository,
								private EntityManagerInterface $entityManager,
								private AlgorithmResolver      $algorithmResolver)
	{
	}

	public function getHolidays(string $language, int $year, ?int $day = null, ?int $month = null, ?string $country = null): array
	{
		$fixed = $this->getFixedHolidays($language, $day, $month, $country);
		$floating = $this->getFloatingHolidays($language, $year, $day, $month, $country);

		$merged = array_merge($fixed, $floating);
		usort($merged, fn(array $a, array $b) => [$a['month'], $a['day']] <=> [$b['month'], $b['day']]);

		return $merged;
	}

	/**
	 * @param array<array<string, mixed>> $holidays Already-sorted flat list
	 * @return HolidayDay[]
	 */
	public function groupByDay(array $holidays): array
	{
		$groups = [];
		foreach ($holidays as $holiday) {
			$key = sprintf('%02d%02d', $holiday['month'], $holiday['day']);
			$groups[$key][] = $holiday;
		}

		$result = [];
		foreach ($groups as $key => $items) {
			$result[] = new HolidayDay($key, $items[0]['day'], $items[0]['month'], $items);
		}

		return $result;
	}

	private function getFixedHolidays(string $language, ?int $day = null, ?int $month = null, ?string $country = null): array
	{
		$holidays = $this->fixedHolidayRepository->findAllByLanguage($language, day: $day, month: $month, country: $country);

		return array_map(fn(array $h) => [
			'id' => 'fixed-' . $h['id'],
			'day' => $h['day'],
			'month' => $h['month'],
			'name' => $h['name'],
			'usual' => $h['usual'],
			'description' => $h['description'],
			'country' => $h['countryCode'],
			'url' => $h['url'],
			'mature_content' => $h['matureContent'],
		], $holidays);
	}

	private function getFloatingHolidays(string $language, int $year, ?int $day = null, ?int $month = null, ?string $country = null): array
	{
		$qb = $this->entityManager->createQueryBuilder()
			->select('h', 'm')
			->from(FloatingHoliday::class, 'h')
			->join('h.metadata', 'm')
			->where('h.language = :language')
			->setParameter('language', $language);

		if ($country !== null) {
			$qb->join('m.country', 'c')
				->andWhere('c.isoCode = :country')
				->setParameter('country', $country);
		}

		/** @var FloatingHoliday[] $holidays */
		$holidays = $qb->getQuery()->getResult();

		$result = [];
		foreach ($holidays as $holiday) {
			$metadata = $holiday->metadata;
			$args = json_decode($metadata->algorithmArgs, true);
			$resolved = $this->algorithmResolver->resolve($metadata->algorithm, $args, $year);

			if ($resolved === null) {
				continue;
			}

			if ($month !== null && $resolved['month'] !== $month) {
				continue;
			}
			if ($day !== null && $resolved['day'] !== $day) {
				continue;
			}

			$result[] = [
				'id' => 'floating-' . $metadata->id,
				'day' => $resolved['day'],
				'month' => $resolved['month'],
				'name' => $holiday->name,
				'usual' => $metadata->usual,
				'description' => $holiday->description,
				'country' => $metadata->country?->isoCode,
				'url' => $holiday->url,
				'mature_content' => $metadata->matureContent,
			];
		}

		return $result;
	}
}
