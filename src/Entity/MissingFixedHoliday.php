<?php

namespace App\Entity;

use App\Repository\MissingFixedHolidayRepository;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: MissingFixedHolidayRepository::class)]
class MissingFixedHoliday implements JsonSerializable {
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id;

	#[ORM\Column]
	private string $userId;

	#[ORM\Column]
	private string $name;

	#[ORM\Column(type: 'text')]
	private string $description;

	#[ORM\Column]
	private int $day;

	#[ORM\Column]
	private int $month;

	#[ORM\OneToOne(targetEntity: FixedHolidayMetadata::class)]
	#[ORM\JoinColumn(name: 'holiday', referencedColumnName: 'id')]
	private ?FixedHolidayMetadata $holiday;

	#[ORM\Column(type: 'string', nullable: false, enumType: ReportState::class)]
	private ReportState $reportState;

	public function __construct(?int $id, string $userId, string $name, string $description, string $day, string $month) {
		$this->id = $id;
		$this->userId = $userId;
		$this->name = $name;
		$this->description = $description;
		$this->day = $day;
		$this->month = $month;
		$this->reportState = ReportState::REPORTED;
		$this->holiday = null;
	}

	public function getId(): ?int {
		return $this->id;
	}

	public function setId(?int $id): void {
		$this->id = $id;
	}

	public function getUserId(): string {
		return $this->userId;
	}

	public function setUserId(string $userId): void {
		$this->userId = $userId;
	}

	public function getDay(): string {
		return $this->day;
	}

	public function setDay(string $day): void {
		$this->day = $day;
	}

	public function getMonth(): string {
		return $this->month;
	}

	public function setMonth(string $month): void {
		$this->month = $month;
	}

	public function getName(): string {
		return $this->name;
	}

	public function setName(string $name): void {
		$this->name = $name;
	}

	public function getDescription(): string {
		return $this->description;
	}

	public function setDescription(string $description): void {
		$this->description = $description;
	}

	public function getReportState(): ReportState {
		return $this->reportState;
	}

	public function setReportState(ReportState $reportState): void {
		$this->reportState = $reportState;
	}

	#[Pure]
	#[Override]
	#[ArrayShape([
		'id' => 'int|null',
		'user_id' => 'string',
		'day' => 'integer',
		'month' => 'integer',
		'name' => 'string',
		'description' => 'string',
		'report_state' => '\App\Entity\ReportState',
		'holiday_id' => 'int|null'
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'day' => $this->day,
			'month' => $this->month,
			'name' => $this->name,
			'description' => $this->description,
			'report_state' => $this->reportState,
			'holiday_id' => $this->holiday?->getId()
		];
	}
}
