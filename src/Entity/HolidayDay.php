<?php

namespace App\Entity;

use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;

class HolidayDay implements JsonSerializable {
	private int $day;
	private int $month;
	private array $holidays;

	public function __construct(int $day, int $month, array $holidays = []) {
		$this->day = $day;
		$this->month = $month;
		$this->holidays = $holidays;
	}

	public function getDay(): int {
		return $this->day;
	}

	public function getMonth(): int {
		return $this->month;
	}

	public function getHolidays(): array {
		return $this->holidays;
	}

	#[ArrayShape([
		'day' => 'int',
		'month' => 'int',
		'holidays' => 'array'
	])]
	public function jsonSerialize(): array {
		return [
			'day' => $this->day,
			'month' => $this->month,
			'holidays' => $this->holidays
		];
	}
}
