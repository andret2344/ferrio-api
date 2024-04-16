<?php

namespace App\Entity;

use App\Repository\FixedHolidayReportRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: FixedHolidayReportRepository::class)]
class FixedHolidayReport implements JsonSerializable {
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private ?int $id;

	#[ORM\ManyToOne(targetEntity: Language::class)]
	#[Orm\JoinColumn(name: 'language_code', referencedColumnName: 'code', nullable: false)]
	private Language $language;

	#[ORM\ManyToOne(targetEntity: FixedHolidayMetadata::class, inversedBy: 'reports')]
	#[Orm\JoinColumn(name: 'metadata_id', referencedColumnName: 'id', nullable: false)]
	private ?FixedHolidayMetadata $metadata;

	#[ORM\Column(type: 'string', nullable: false, enumType: ReportType::class)]
	private ReportType $reportType;

	#[ORM\Column(type: 'json', nullable: false)]
	private ?array $data;

	#[ORM\Column(type: 'text', length: 65536, nullable: true)]
	private ?string $additionalDescription;

	#[ORM\Column(type: 'datetimetz_immutable', nullable: false)]
	private readonly DateTimeImmutable $datetime;

	#[ORM\Column(type: 'boolean', nullable: false)]
	private bool $verified;

	public function __construct(?int                 $id,
								Language             $language,
								FixedHolidayMetadata $metadata,
								ReportType           $reportType,
								?array               $data,
								?string              $additionalDescription) {
		$this->id = $id;
		$this->language = $language;
		$this->metadata = $metadata;
		$this->reportType = $reportType;
		$this->data = $data;
		$this->additionalDescription = $additionalDescription;
		$this->datetime = new DateTimeImmutable();
		$this->verified = false;
	}

	public function getId(): int {
		return $this->id;
	}

	public function setId(int $id): void {
		$this->id = $id;
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

	public function getData(): ?array {
		return $this->data;
	}

	public function setData(?array $data): void {
		$this->data = $data;
	}

	public function getAdditionalDescription(): ?string {
		return $this->additionalDescription;
	}

	public function setAdditionalDescription(?string $additionalDescription): void {
		$this->additionalDescription = $additionalDescription;
	}

	public function getDatetime(): DateTimeImmutable {
		return $this->datetime;
	}

	public function isVerified(): bool {
		return $this->verified;
	}

	#[Pure]
	#[Override]
	#[ArrayShape([
		'id' => "int",
		'language_code' => "\App\Entity\Language",
		'metadata_id' => "int",
		'report_type' => "\App\Entity\ReportType",
		'data' => "array|null",
		'additional_description' => "null|string",
		'datetime' => "null|string",
		'verified' => "bool"
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'language_code' => $this->language->getCode(),
			'metadata_id' => $this->metadata->getId(),
			'report_type' => $this->reportType,
			'data' => $this->data,
			'additional_description' => $this->additionalDescription,
			'datetime' => $this->datetime->format('Y-m-d H:i:s'),
			'verified' => $this->verified
		];
	}
}
