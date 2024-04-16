<?php

namespace App\Entity;

use App\Repository\FixedHolidayReportRepository;
use App\Repository\FloatingHolidayReportRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: FloatingHolidayReportRepository::class)]
class FloatingHolidayReport implements JsonSerializable {
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private ?int $id;

	#[ORM\ManyToOne(targetEntity: Language::class)]
	#[Orm\JoinColumn(name: 'language_code', referencedColumnName: 'code', nullable: false)]
	private Language $language;

	#[ORM\ManyToOne(targetEntity: FloatingHolidayMetadata::class, inversedBy: 'reports')]
	#[Orm\JoinColumn(name: 'metadata_id', referencedColumnName: 'id', nullable: false)]
	private ?FloatingHolidayMetadata $metadata;

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

	public function __construct(?int                    $id,
								Language                $language,
								FloatingHolidayMetadata $metadata,
								ReportType              $reportType,
								?array                  $data,
								?string                 $additionalDescription) {
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

	public function getMetadata(): FloatingHolidayMetadata {
		return $this->metadata;
	}

	public function setMetadata(?FloatingHolidayMetadata $metadata): void {
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

	#[Pure]
	#[Override]
	#[ArrayShape([
		'id' => "int",
		'language' => "\App\Entity\Language",
		'metadataId' => "int",
		'reportType' => "\App\Entity\ReportType",
		'data' => "array|null",
		'additionalDescription' => "null|string",
		'datetime' => "null|string",
		'verified' => "bool"
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'language' => $this->language,
			'metadataId' => $this->metadata->getId(),
			'reportType' => $this->reportType,
			'data' => $this->data,
			'additionalDescription' => $this->additionalDescription,
			'datetime' => $this->datetime->format('Y-m-d H:i:s'),
			'verified' => $this->verified
		];
	}
}
