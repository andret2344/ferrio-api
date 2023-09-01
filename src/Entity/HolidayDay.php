<?php

namespace App\Entity;

use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;

class HolidayDay implements JsonSerializable {
	private int $id;
	private int $day;
	private int $month;
	private array $holidays;

	public function __construct(int $id, int $day, int $month, array $holidays = []) {
		$this->id = $id;
		$this->day = $day;
		$this->month = $month;
		$this->holidays = $holidays;
	}

	public function getId(): int {
		return $this->id;
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
		'id' => 'int',
		'day' => 'int',
		'month' => 'int',
		'holidays' => 'array'
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'day' => $this->day,
			'month' => $this->month,
			'holidays' => $this->holidays
		];
	}
}
