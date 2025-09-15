<?php

namespace App\Service;

use App\Entity\FixedHoliday;
use App\Entity\FloatingHoliday;
use App\Entity\HolidayDay;
use App\Entity\Script;
use App\Repository\FixedHolidayRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class HolidayService
{
	public function __construct(private FixedHolidayRepository $holidayRepository,
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
		/** @var FixedHoliday[] $holidays */
		$holidays = $this->holidayRepository->findAllByLanguage($language);
		$days = [];
		$day = 1;
		$month = 1;
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
				'description' => '', //$holiday['description'],
				'country_name' => $holiday['countryName'],
				'country_code' => $holiday['countryCode'],
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
			$metadata = $holiday->getMetadata();
			$script = $metadata->script;
			$args = implode(', ', json_decode($metadata->args));
			$script = new Script($script->id, $script->content . "\n\ncalculate($args);");
			$country = $metadata->country;
			$data[] = [
				...$holiday->jsonSerialize(),
				'country_code' => $country->isoCode,
				'country_name' => $country->englishName,
				'script' => $script->content
			];
		}
		return $data;
	}

	public function getHolidayDay(string $language, int $day, int $month): ?HolidayDay
	{
		$id = sprintf('%02d', $month) . sprintf('%02d', $day);
		$holidays = $this->holidayRepository->findAt($language, $day, $month);
		return new HolidayDay($id, $day, $month, $holidays);
	}
}
