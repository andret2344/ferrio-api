<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Override;

#[ORM\Entity]
class FixedHolidayError implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private(set) ?int $id;

	#[ORM\Column(type: 'string')]
	private(set) string $userId;

	#[ORM\ManyToOne(targetEntity: Language::class)]
	#[ORM\JoinColumn(name: 'language_code', referencedColumnName: 'code')]
	private(set) Language $language;

	#[ORM\ManyToOne(targetEntity: FixedHolidayMetadata::class, inversedBy: 'reports')]
	#[ORM\JoinColumn(name: 'metadata_id', referencedColumnName: 'id')]
	public ?FixedHolidayMetadata $metadata;

	#[ORM\Column(type: 'string', enumType: ReportType::class)]
	private(set) ReportType $reportType;

	#[ORM\Column(type: 'text', length: 65536, nullable: true)]
	private(set) ?string $description;

	#[ORM\Column(type: 'datetimetz_immutable')]
	private(set) DateTimeImmutable $datetime;

	#[ORM\Column(type: 'string', enumType: ReportState::class)]
	private(set) ReportState $reportState;

	public function __construct(string               $userId,
								Language             $language,
								FixedHolidayMetadata $metadata,
								ReportType           $reportType,
								?string              $description)
	{
		$this->userId = $userId;
		$this->language = $language;
		$this->metadata = $metadata;
		$this->reportType = $reportType;
		$this->description = $description;
		$this->datetime = new DateTimeImmutable();
		$this->reportState = ReportState::REPORTED;
	}

	#[Override]
	#[ArrayShape([
		'id' => 'int',
		'user_id' => 'string',
		'language_code' => 'string',
		'metadata_id' => 'int',
		'report_type' => '\App\Entity\ReportType',
		'description' => 'null|string',
		'datetime' => 'string',
		'report_state' => '\App\Entity\ReportState'
	])]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'language_code' => $this->language->code,
			'metadata_id' => $this->metadata->id,
			'report_type' => $this->reportType,
			'description' => $this->description,
			'datetime' => $this->datetime->format('Y-m-d H:i:s'),
			'report_state' => $this->reportState
		];
	}
}
