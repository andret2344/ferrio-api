<?php

namespace App\Service;

use App\Entity\FixedHoliday;
use App\Entity\FloatingHoliday;
use App\Entity\HolidayDay;
use App\Entity\Script;
use App\Repository\FixedHolidayRepository;
use App\Repository\FloatingHolidayRepository;

readonly class HolidayService {
	public function __construct(private FixedHolidayRepository    $holidayRepository,
								private FloatingHolidayRepository $floatingHolidayRepository) {
	}

	/**
	 * @param string $language
	 * @return array|HolidayDay[]
	 */
	public function getHolidays(string $language): array {
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
				'description' => $holiday['description'],
				'country' => $holiday['country'],
				'url' => $holiday['url'],
				'link' => $holiday['url']
			];
		}
		$id = sprintf('%02d', $month) . sprintf('%02d', $day);
		$days[] = new HolidayDay($id, $day, $month, $array);
		return $days;
	}

	/**
	 * @param string $language
	 * @return array
	 */
	public function getFloatingHolidays(string $language): array {
		/** @var FloatingHoliday[] $holidays */
		$holidays = $this->floatingHolidayRepository->findBy(['language' => $language]);
		$data = [];
		foreach ($holidays as $holiday) {
			$script = $holiday->getMetadata()->getScript();
			$args = implode(', ', json_decode($holiday->getMetadata()->getArgs()));
			$script = new Script($script->getId(), $script->getContent() . "\n\ncalculate($args);");
			$data[] = [
				...$holiday->jsonSerialize(),
				'country' => $holiday->getMetadata()->getCountry()?->getEnglishName(),
				'script' => $script->getContent()
			];
		}
		return $data;
	}

	public function getHoliday(string $language, int $id): ?FixedHoliday {
		return $this->holidayRepository->findOneBy([
			'language' => $language,
			'metadata' => $id
		]);
	}

	public function getTodayHoliday(string $language): ?HolidayDay {
		$day = +date('j');
		$month = +date('m');
		return $this->getHolidayDay($language, $day, $month);
	}

	public function getHolidayDay(string $language, int $day, int $month): ?HolidayDay {
		$id = sprintf('%02d', $month) . sprintf('%02d', $day);
		$holidays = $this->holidayRepository->findAt($language, $day, $month);
		return new HolidayDay($id, $day, $month, $holidays);
	}
}
