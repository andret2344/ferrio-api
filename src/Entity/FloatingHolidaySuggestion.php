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
	private(set) ?int $id;

	#[ORM\Column(type: 'string')]
	private(set) string $userId;

	#[ORM\Column(type: 'string')]
	private(set) string $name;

	#[ORM\Column(type: 'text')]
	private(set) string $description;

	#[ORM\Column(type: 'text')]
	private(set) string $date;

	#[ORM\ManyToOne(targetEntity: Country::class)]
	#[ORM\JoinColumn(name: 'country', referencedColumnName: 'iso_code', nullable: true)]
	private(set) ?Country $country;

	#[ORM\Column(type: 'datetimetz_immutable')]
	private(set) DateTimeImmutable $datetime;

	#[ORM\Column(type: 'string', enumType: ReportState::class)]
	private(set) ReportState $reportState;

	#[ORM\OneToOne(targetEntity: FloatingHolidayMetadata::class)]
	#[ORM\JoinColumn(name: 'holiday', referencedColumnName: 'id')]
	private(set) ?FloatingHolidayMetadata $holiday;

	public function __construct(string      $userId, string $name, string $description, string $date,
								?Country    $country = null, DateTimeImmutable $datetime = new DateTimeImmutable(),
								ReportState $reportState = ReportState::REPORTED, ?FixedHolidayMetadata $fixedHolidayMetadata = null)
	{
		$this->userId = $userId;
		$this->name = $name;
		$this->description = $description;
		$this->date = $date;
		$this->country = $country;
		$this->datetime = $datetime;
		$this->reportState = $reportState;
		$this->holiday = $fixedHolidayMetadata;
	}

	#[Override]
	#[ArrayShape([
		'id' => 'int|null',
		'user_id' => 'string',
		'name' => 'string',
		'description' => 'string',
		'date' => 'string',
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
			'name' => $this->name,
			'description' => $this->description,
			'date' => $this->date,
			'datetime' => $this->datetime->format('Y-m-d H:i:s'),
			'country' => $this->country?->isoCode,
			'report_state' => $this->reportState,
			'holiday_id' => $this->holiday?->id
		];
	}
}
