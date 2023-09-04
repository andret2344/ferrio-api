<?php

namespace App\Service;

use App\Entity\Holiday;
use App\Entity\HolidayDay;
use App\Repository\HolidayRepository;

readonly class HolidayService {
	public function __construct(private HolidayRepository $holidayRepository) {
	}

	public function getHolidays(string $language): array {
		/** @var Holiday[] $holidays */
		$holidays = $this->holidayRepository->findBy([
			'language' => $language
		]);
		$days = [];
		$day = 1;
		$month = 1;
		$array = [];
		foreach ($holidays as $holiday) {
			if ($day != $holiday->getMetadata()->getDay() || $month != $holiday->getMetadata()->getMonth()) {
				$id = sprintf('%02d', $month) . sprintf('%02d', $day);
				$days[] = new HolidayDay($id, $day, $month, $array);
				$array = [];
				$day = $holiday->getMetadata()->getDay();
				$month = $holiday->getMetadata()->getMonth();
			}
			$array[] = $holiday;
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
		$id = sprintf('%02d', $month) . sprintf('%02d', $day);
		$holidays = $this->holidayRepository->findAt($language, $day, $month);
		return new HolidayDay($id, $day, $month, $holidays);
	}
}
