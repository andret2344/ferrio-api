<?php

namespace App\Service;

use App\Entity\FloatingHoliday;
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
		$fixed = $this->getFixedHolidays($language);
		$floating = $this->getFloatingHolidays($language, $year);

		$merged = array_merge($fixed, $floating);

		if ($month !== null) {
			$merged = array_filter($merged, fn(array $h) => $h['month'] === $month);
		}
		if ($day !== null) {
			$merged = array_filter($merged, fn(array $h) => $h['day'] === $day);
		}
		if ($country !== null) {
			$merged = array_filter($merged, fn(array $h) => $h['country'] === $country);
		}

		$merged = array_values($merged);
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

	private function getFixedHolidays(string $language): array
	{
		$holidays = $this->fixedHolidayRepository->findAllByLanguage($language);

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

	private function getFloatingHolidays(string $language, int $year): array
	{
		/** @var FloatingHoliday[] $holidays */
		$holidays = $this->entityManager->getRepository(FloatingHoliday::class)
			->findBy(['language' => $language]);

		$result = [];
		foreach ($holidays as $holiday) {
			$metadata = $holiday->metadata;
			$args = json_decode($metadata->algorithmArgs, true);
			$resolved = $this->algorithmResolver->resolve($metadata->algorithm, $args, $year);

			if ($resolved === null) {
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
