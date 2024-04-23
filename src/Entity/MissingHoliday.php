<?php

namespace App\Entity;

use App\Repository\MissingHolidayRepository;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: MissingHolidayRepository::class)]
class MissingHoliday implements JsonSerializable {
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private ?int $id;

	#[ORM\Column(type: 'string', nullable: false)]
	private string $userId;

	#[ORM\ManyToOne(targetEntity: Language::class)]
	#[ORM\JoinColumn(name: 'language_code', referencedColumnName: 'code', nullable: false)]
	private Language $language;

	#[ORM\Column(type: 'string', nullable: false)]
	private string $name;

	#[ORM\Column(type: 'string', nullable: false)]
	private string $description;

	#[ORM\Column(type: 'string', nullable: false, enumType: ReportState::class)]
	private ReportState $reportState;

	public function __construct(?int $id, string $userId, Language $language, string $name, string $description) {
		$this->id = $id;
		$this->userId = $userId;
		$this->language = $language;
		$this->name = $name;
		$this->description = $description;
		$this->reportState = ReportState::REPORTED;
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

	public function getLanguage(): Language {
		return $this->language;
	}

	public function setLanguage(Language $language): void {
		$this->language = $language;
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
		'userId' => 'string',
		'language' => 'string',
		'name' => 'string',
		'description' => 'string',
		'reportState' => '\App\Entity\ReportState'
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'userId' => $this->userId,
			'language' => $this->language->getCode(),
			'name' => $this->name,
			'description' => $this->description,
			'reportState' => $this->reportState,
		];
	}
}
