<?php

namespace App\Entity;

use App\Repository\FixedHolidayReportRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: FixedHolidayReportRepository::class)]
class FixedHolidayReport implements JsonSerializable {
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private ?int $id;

	#[ORM\Column(type: 'string', nullable: false)]
	private string $userId;

	#[ORM\ManyToOne(targetEntity: Language::class)]
	#[ORM\JoinColumn(name: 'language_code', referencedColumnName: 'code', nullable: false)]
	private Language $language;

	#[ORM\ManyToOne(targetEntity: FixedHolidayMetadata::class, inversedBy: 'reports')]
	#[ORM\JoinColumn(name: 'metadata_id', referencedColumnName: 'id', nullable: false)]
	private ?FixedHolidayMetadata $metadata;

	#[ORM\Column(type: 'string', nullable: false, enumType: ReportType::class)]
	private ReportType $reportType;

	#[ORM\Column(type: 'text', length: 65536, nullable: true)]
	private ?string $description;

	#[ORM\Column(type: 'datetimetz_immutable', nullable: false)]
	private readonly DateTimeImmutable $datetime;

	#[ORM\Column(type: 'string', nullable: false, enumType: ReportType::class)]
	private ReportState $reportState;

	public function __construct(?int                 $id,
								string               $userId,
								Language             $language,
								FixedHolidayMetadata $metadata,
								ReportType           $reportType,
								?string              $description) {
		$this->id = $id;
		$this->userId = $userId;
		$this->language = $language;
		$this->metadata = $metadata;
		$this->reportType = $reportType;
		$this->description = $description;
		$this->datetime = new DateTimeImmutable();
		$this->reportState = ReportState::REPORTED;
	}

	public function getId(): int {
		return $this->id;
	}

	public function setId(int $id): void {
		$this->id = $id;
	}

	public function getUserId(): string {
		return $this->userId;
	}

	public function setUserId(string $userId): void {
		$this->userId = $userId;
	}

	public function getLanguage(): Language {
		return $this->language;
	}

	public function setLanguage(Language $language): void {
		$this->language = $language;
	}

	public function getMetadata(): FixedHolidayMetadata {
		return $this->metadata;
	}

	public function setMetadata(?FixedHolidayMetadata $metadata): void {
		$this->metadata = $metadata;
	}

	public function getReportType(): ReportType {
		return $this->reportType;
	}

	public function setReportType(ReportType $reportType): void {
		$this->reportType = $reportType;
	}

	public function getDescription(): ?string {
		return $this->description;
	}

	public function setDescription(?string $description): void {
		$this->description = $description;
	}

	public function getDatetime(): DateTimeImmutable {
		return $this->datetime;
	}

	public function getReportState(): ReportState {
		return $this->reportState;
	}

	public function setReportState(ReportState $reportState): void {
		$this->reportState = $reportState;
	}

	#[Override]
	#[ArrayShape([
		'id' => "int",
		'user_id' => 'string',
		'language_code' => "\App\Entity\Language",
		'metadata_id' => "int",
		'report_type' => "\App\Entity\ReportType",
		'description' => "null|string",
		'datetime' => "null|string",
		'report_state' => "\App\Entity\ReportState"
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'language_code' => $this->language->getCode(),
			'metadata_id' => $this->metadata->getId(),
			'report_type' => $this->reportType,
			'description' => $this->description,
			'datetime' => $this->datetime->format('Y-m-d H:i:s'),
			'report_state' => $this->reportState
		];
	}
}
