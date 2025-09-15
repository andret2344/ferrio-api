<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Override;

#[ORM\Entity]
class FloatingHolidaySuggestion implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id;

	#[ORM\Column(type: 'string')]
	private string $userId;

	#[ORM\Column(type: 'string')]
	private string $name;

	#[ORM\Column(type: 'text')]
	private string $description;

	#[ORM\Column(type: 'text')]
	private string $date;

	#[ORM\Column(type: 'datetimetz_immutable', nullable: false)]
	private readonly DateTimeImmutable $datetime;

	#[ORM\Column(type: 'string', nullable: false, enumType: ReportState::class)]
	private ReportState $reportState;

	#[ORM\OneToOne(targetEntity: FloatingHolidayMetadata::class)]
	#[ORM\JoinColumn(name: 'holiday', referencedColumnName: 'id')]
	private ?FloatingHolidayMetadata $holiday;

	public function __construct(string $userId, string $name, string $description, string $date, DateTimeImmutable $datetime)
	{
		$this->userId = $userId;
		$this->name = $name;
		$this->description = $description;
		$this->date = $date;
		$this->datetime = $datetime;
		$this->reportState = ReportState::REPORTED;
		$this->holiday = null;
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function setId(?int $id): void
	{
		$this->id = $id;
	}

	public function getUserId(): string
	{
		return $this->userId;
	}

	public function setUserId(string $userId): void
	{
		$this->userId = $userId;
	}

	public function getDate(): string
	{
		return $this->date;
	}

	public function setDate(string $date): void
	{
		$this->date = $date;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}

	public function getDescription(): string
	{
		return $this->description;
	}

	public function setDescription(string $description): void
	{
		$this->description = $description;
	}

	public function getDatetime(): DateTimeImmutable
	{
		return $this->datetime;
	}

	public function getReportState(): ReportState
	{
		return $this->reportState;
	}

	public function setReportState(ReportState $reportState): void
	{
		$this->reportState = $reportState;
	}

	#[Override]
	#[ArrayShape([
		'id' => 'int|null',
		'user_id' => 'string',
		'name' => 'string',
		'description' => 'string',
		'date' => 'string',
		'datetime' => 'string',
		'report_state' => '\App\Entity\ReportState',
		'holiday_id' => 'int|null'
	])]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'name' => $this->name,
			'description' => $this->description,
			'date' => $this->date,
			'datetime' => $this->datetime->format('Y-m-d H:i:s'),
			'report_state' => $this->reportState,
			'holiday_id' => $this->holiday?->id
		];
	}
}
