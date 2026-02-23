<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Override;

#[ORM\Entity]
class FixedHolidaySuggestion implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private(set) ?int $id;

	#[ORM\Column]
	private(set) string $userId;

	#[ORM\Column]
	private(set) string $name;

	#[ORM\Column(type: 'text')]
	private(set) string $description;

	#[ORM\Column]
	private(set) int $day;

	#[ORM\Column]
	private(set) int $month;

	#[ORM\ManyToOne(targetEntity: Country::class)]
	#[ORM\JoinColumn(name: 'country', referencedColumnName: 'iso_code', nullable: true)]
	private(set) ?Country $country;

	#[ORM\Column(type: 'datetimetz_immutable')]
	private(set) DateTimeImmutable $datetime;

	#[ORM\Column(type: 'string', enumType: ReportState::class)]
	private(set) ReportState $reportState;

	#[ORM\OneToOne(targetEntity: FixedHolidayMetadata::class)]
	#[ORM\JoinColumn(name: 'holiday', referencedColumnName: 'id')]
	private(set) ?FixedHolidayMetadata $holiday;

	public function __construct(string      $userId, string $name, string $description, int $day, int $month,
								?Country    $country = null, DateTimeImmutable $datetime = new DateTimeImmutable(),
								ReportState $reportState = ReportState::REPORTED, ?FixedHolidayMetadata $holiday = null)
	{
		$this->userId = $userId;
		$this->name = $name;
		$this->description = $description;
		$this->day = $day;
		$this->month = $month;
		$this->country = $country;
		$this->datetime = $datetime;
		$this->reportState = $reportState;
		$this->holiday = $holiday;
	}

	#[Override]
	#[ArrayShape([
		'id' => 'int|null',
		'user_id' => 'string',
		'day' => 'integer',
		'month' => 'integer',
		'name' => 'string',
		'description' => 'string',
		'datetime' => 'string',
		'country' => 'null|string',
		'report_state' => '\App\Entity\ReportState',
		'holiday_id' => 'int|null'
	])]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'day' => $this->day,
			'month' => $this->month,
			'name' => $this->name,
			'description' => $this->description,
			'datetime' => $this->datetime->format('Y-m-d H:i:s'),
			'country' => $this->country?->isoCode,
			'report_state' => $this->reportState,
			'holiday_id' => $this->holiday?->id
		];
	}
}
