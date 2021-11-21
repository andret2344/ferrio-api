<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class HolidayMetadata {
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: "integer")]
	private int $id;

	#[ORM\Column(type: "integer")]
	private int $month;

	#[ORM\Column(type: "integer")]
	private int $day;

	#[ORM\Column(type: "boolean")]
	private int $usual;

	public function __construct(int $id, int $month, int $day, int $usual) {
		$this->id = $id;
		$this->month = $month;
		$this->day = $day;
		$this->usual = $usual;
	}

	public function getId(): int {
		return $this->id;
	}

	public function getMonth(): int {
		return $this->month;
	}

	public function getDay(): int {
		return $this->day;
	}

	public function getUsual(): int {
		return $this->usual;
	}
}
