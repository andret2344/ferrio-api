<?php

namespace App\Entity;

use App\Repository\MissingFloatingHolidayRepository;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: MissingFloatingHolidayRepository::class)]
class MissingFloatingHoliday implements JsonSerializable {
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private ?int $id;

	#[ORM\Column(type: 'string', nullable: false)]
	private string $userId;

	#[ORM\Column(type: 'string', nullable: false)]
	private string $name;

	#[ORM\Column(type: 'text', length: 65536, nullable: false)]
	private string $description;

	#[ORM\Column(type: 'text', length: 65536, nullable: false)]
	private string $date;

	#[ORM\Column(type: 'string', nullable: false, enumType: ReportState::class)]
	private ReportState $reportState;

	#[ORM\OneToOne(targetEntity: FloatingHolidayMetadata::class)]
	#[ORM\Column(nullable: true)]
	private ?FloatingHolidayMetadata $holiday;

	public function __construct(?int $id, string $userId, string $name, string $description, string $date) {
		$this->id = $id;
		$this->userId = $userId;
		$this->name = $name;
		$this->description = $description;
		$this->date = $date;
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

	public function getDate(): string {
		return $this->date;
	}

	public function setDate(string $date): void {
		$this->date = $date;
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
		'name' => 'string',
		'description' => 'string',
		'date' => 'string',
		'report_state' => '\App\Entity\ReportState',
		'holiday_id' => 'int|null'
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'name' => $this->name,
			'description' => $this->description,
			'date' => $this->date,
			'report_state' => $this->reportState,
			'holiday_id' => $this->holiday?->getId()
		];
	}
}
