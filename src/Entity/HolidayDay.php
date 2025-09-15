<?php

namespace App\Entity;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Override;

class HolidayDay implements JsonSerializable
{
	private(set) string $id;
	private(set) int $day;
	private(set) int $month;
	private(set) array $holidays;

	public function __construct(string $id, int $day, int $month, array $holidays = [])
	{
		$this->id = $id;
		$this->day = $day;
		$this->month = $month;
		$this->holidays = $holidays;
	}

	#[Pure]
	#[Override]
	#[ArrayShape([
		'id' => 'string',
		'day' => 'int',
		'month' => 'int',
		'holidays' => 'array'
	])]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'day' => $this->day,
			'month' => $this->month,
			'holidays' => $this->holidays
		];
	}
}
