<?php

namespace App\Service;

use App\Entity\HolidayDay;
use App\Repository\FixedHolidayRepository;
use App\Repository\FloatingHolidayRepository;

readonly class HolidayService
{
	public function __construct(
		private FixedHolidayRepository    $fixedHolidayRepository,
		private FloatingHolidayRepository $floatingHolidayRepository)
	{
	}

	/**
	 * @param string $language
	 *
	 * @return array|HolidayDay[]
	 */
	public function getHolidays(string $language): array
	{
		$holidays = $this->fixedHolidayRepository->findAllByLanguage($language);
		$groups = [];
		foreach ($holidays as $holiday) {
			$key = HolidayDay::formatId($holiday['day'], $holiday['month']);
			$groups[$key] ??= ['day' => $holiday['day'], 'month' => $holiday['month'], 'items' => []];
			$groups[$key]['items'][] = [
				'id' => $holiday['id'],
				'name' => $holiday['name'],
				'usual' => $holiday['usual'],
				'description' => '',
				'country' => $holiday['countryCode'],
				'url' => $holiday['url'],
				'mature_content' => $holiday['matureContent'],
			];
		}

		$days = [];
		foreach ($groups as $key => $g) {
			$days[] = new HolidayDay($key, $g['day'], $g['month'], $g['items']);
		}
		return $days;
	}

	/**
	 * @param string $language
	 *
	 * @return array
	 */
	public function getFloatingHolidays(string $language): array
	{
		$holidays = $this->floatingHolidayRepository->findAllByLanguage($language);
		$data = [];
		foreach ($holidays as $holiday) {
			$metadata = $holiday->metadata;
			if ($metadata->script === null || $metadata->script->content === '' || $metadata->args === '') {
				continue;
			}
			$args = implode(', ', json_decode($metadata->args));
			$scriptContent = $metadata->script->content . "\n\ncalculate($args);";
			$country = $metadata->country;
			$data[] = [
				...$holiday->jsonSerialize(),
				'country' => $country?->isoCode,
				'script' => $scriptContent
			];
		}
		return $data;
	}

	public function getHolidayDay(string $language, int $day, int $month): HolidayDay
	{
		$id = HolidayDay::formatId($day, $month);
		$holidays = $this->fixedHolidayRepository->findAt($language, $day, $month);
		return new HolidayDay($id, $day, $month, $holidays);
	}
}
