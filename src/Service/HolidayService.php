<?php

namespace App\Service;

use App\Entity\FloatingHoliday;
use App\Entity\HolidayDay;
use App\Repository\FixedHolidayRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class HolidayService
{
	public function __construct(private FixedHolidayRepository $fixedHolidayRepository,
								private EntityManagerInterface $entityManager)
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
		if (empty($holidays)) {
			return [];
		}
		$days = [];
		$day = $holidays[0]['day'];
		$month = $holidays[0]['month'];
		$array = [];
		foreach ($holidays as $holiday) {
			if ($day != $holiday['day'] || $month != $holiday['month']) {
				$id = sprintf('%02d', $month) . sprintf('%02d', $day);
				$days[] = new HolidayDay($id, $day, $month, $array);
				$array = [];
				$day = $holiday['day'];
				$month = $holiday['month'];
			}
			$array[] = [
				'id' => $holiday['id'],
				'name' => $holiday['name'],
				'usual' => $holiday['usual'],
				// frontend doesn't work with description, commented-out on purpose
				'description' => '', //$holiday['description'],
				'country' => $holiday['countryCode'],
				'url' => $holiday['url'],
				'mature_content' => $holiday['matureContent'],
			];
		}
		$id = sprintf('%02d', $month) . sprintf('%02d', $day);
		$days[] = new HolidayDay($id, $day, $month, $array);
		return $days;
	}

	/**
	 * @param string $language
	 *
	 * @return array
	 */
	public function getFloatingHolidays(string $language): array
	{
		/** @var FloatingHoliday[] $holidays */
		$holidays = $this->entityManager->getRepository(FloatingHoliday::class)
			->findBy(['language' => $language]);
		$data = [];
		foreach ($holidays as $holiday) {
			$metadata = $holiday->metadata;
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

	public function getHolidayDay(string $language, int $day, int $month): ?HolidayDay
	{
		$id = sprintf('%02d', $month) . sprintf('%02d', $day);
		$holidays = $this->fixedHolidayRepository->findAt($language, $day, $month);
		return new HolidayDay($id, $day, $month, $holidays);
	}
}
