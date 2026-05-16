<?php

namespace App\Service;

use App\Entity\FloatingHoliday;
use App\Entity\HolidayDay;
use App\Repository\FixedHolidayRepository;
use App\Repository\FixedMetadataRepository;
use App\Repository\FloatingHolidayRepository;
use App\Repository\FloatingMetadataRepository;

readonly class HolidayServiceV3
{
	public function __construct(
		private FixedHolidayRepository     $fixedHolidayRepository,
		private FixedMetadataRepository    $fixedMetadataRepository,
		private FloatingHolidayRepository  $floatingHolidayRepository,
		private FloatingMetadataRepository $floatingMetadataRepository,
		private AlgorithmResolver          $algorithmResolver)
	{
	}

	public function getHolidays(string $language, int $year, ?int $day = null, ?int $month = null, ?string $country = null, bool $includeMatureContent = false): array
	{
		$fixed = $this->getFixedHolidays($language, $day, $month, $country, $includeMatureContent);
		$floating = $this->getFloatingHolidays($language, $year, $day, $month, $country, $includeMatureContent);

		$merged = array_merge($fixed, $floating);
		usort($merged, fn(array $a, array $b) => [$a['month'], $a['day']] <=> [$b['month'], $b['day']]);

		return $merged;
	}

	/**
	 * @param array<array<string, mixed>> $holidays Already-sorted flat list
	 *
	 * @return HolidayDay[]
	 */
	public function groupByDay(array $holidays): array
	{
		$groups = [];
		foreach ($holidays as $holiday) {
			$key = HolidayDay::formatId($holiday['day'], $holiday['month']);
			$groups[$key][] = $holiday;
		}

		$result = [];
		foreach ($groups as $key => $items) {
			$result[] = new HolidayDay($key, $items[0]['day'], $items[0]['month'], $items);
		}

		return $result;
	}

	private function getFixedHolidays(string $language, ?int $day = null, ?int $month = null, ?string $country = null, bool $includeMatureContent = false): array
	{
		$holidays = $this->fixedHolidayRepository->findAllByLanguage($language, matureContent: $includeMatureContent, day: $day, month: $month, country: $country);
		$categoriesByMetadata = $this->fixedMetadataRepository->findCategoryNames(
			array_column($holidays, 'id'),
			$language,
		);

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
			'categories' => $categoriesByMetadata[$h['id']] ?? [],
		], $holidays);
	}

	private function getFloatingHolidays(string $language, int $year, ?int $day = null, ?int $month = null, ?string $country = null, bool $includeMatureContent = false): array
	{
		$holidays = $this->floatingHolidayRepository->findAllByLanguage($language, $country);

		$categoriesByMetadata = $this->floatingMetadataRepository->findCategoryNames(
			array_map(fn(FloatingHoliday $h) => $h->metadata->id, $holidays),
			$language,
		);

		$result = [];
		foreach ($holidays as $holiday) {
			$metadata = $holiday->metadata;
			if ($metadata->algorithmArgs === null || $metadata->algorithmArgs === '') {
				continue;
			}
			if ($metadata->matureContent && !$includeMatureContent) {
				continue;
			}
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
				'categories' => $categoriesByMetadata[$metadata->id] ?? [],
			];
		}

		return $result;
	}
}
