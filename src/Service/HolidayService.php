<?php

namespace App\Service;

use App\Entity\Holiday;
use App\Entity\HolidayDay;
use App\Repository\HolidayRepository;

readonly class HolidayService {
	public function __construct(private HolidayRepository $holidayRepository) {
	}

	/**
	 * @param string $language
	 * @return array|HolidayDay[]
	 */
	public function getHolidays(string $language): array {
		/** @var Holiday[] $holidays */
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
				'url' => $holiday['url']
			];
		}
		return $days;
	}

	public function getHoliday(string $language, int $id): ?Holiday {
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
