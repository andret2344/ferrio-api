<?php

namespace App\Entity;

use App\Repository\FixedMetadataRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: FixedMetadataRepository::class)]
class FixedHolidayMetadata implements JsonSerializable {
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private ?int $id;

	#[ORM\Column(type: 'integer')]
	private int $month;

	#[ORM\Column(type: 'integer')]
	private int $day;

	#[ORM\Column(type: 'boolean')]
	private bool $usual;

	#[ORM\ManyToOne(targetEntity: Category::class)]
	#[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
	private ?Category $category;

	#[ORM\ManyToOne(targetEntity: Country::class, inversedBy: 'fixedHolidays')]
	#[ORM\JoinColumn(name: 'country_code', referencedColumnName: 'iso_code', nullable: true)]
	private ?Country $country;

	#[ORM\OneToMany(mappedBy: 'metadata', targetEntity: FixedHoliday::class, cascade: ['all'], orphanRemoval: true)]
	private Collection $holidays;

	#[ORM\OneToMany(mappedBy: 'metadata', targetEntity: FixedHolidayReport::class, cascade: ['all'], orphanRemoval: true)]
	private Collection $reports;

	#[Pure]
	public function __construct(?int $id, int $month, int $day, int $usual, ?Country $country, ?Category $category) {
		$this->id = $id;
		$this->month = $month;
		$this->day = $day;
		$this->usual = $usual;
		$this->country = $country;
		$this->category = $category;
		$this->holidays = new ArrayCollection();
		$this->reports = new ArrayCollection();
	}

	public function getId(): ?int {
		return $this->id;
	}

	public function getMonth(): int {
		return $this->month;
	}

	public function getDay(): int {
		return $this->day;
	}

	public function getUsual(): int {
		return $this->usual;
	}

	public function getHolidays(): Collection {
		return $this->holidays;
	}

	public function getCategory(): ?Category {
		return $this->category;
	}

	public function setCategory(?Category $category): void {
		$this->category = $category;
	}

	public function getCountry(): ?Country {
		return $this->country;
	}

	public function setCountry(?Country $country): void {
		$this->country = $country;
	}

	public function addHoliday(FixedHoliday $holiday): self {
		if (!$this->holidays->contains($holiday)) {
			$this->holidays[] = $holiday;
			$holiday->setMetadata($this);
		}
		return $this;
	}

	public function removeHoliday(FixedHoliday $holiday): self {
		if ($this->holidays->removeElement($holiday) && $holiday->getMetadata() === $this) {
			$holiday->setMetadata(null);
		}
		return $this;
	}

	public function addReport(FixedHolidayReport $report): self {
		if (!$this->reports->contains($report)) {
			$this->reports[] = $report;
			$report->setMetadata($this);
		}
		return $this;
	}

	public function removeReport(FixedHolidayReport $report): self {
		if ($this->holidays->removeElement($report) && $report->getMetadata() === $this) {
			$report->setMetadata(null);
		}
		return $this;
	}

	#[Pure]
	#[Override]
	#[ArrayShape([
		'id' => 'int|null',
		'month' => 'int',
		'day' => 'int',
		'usual' => 'int',
		'country' => 'string|null',
		'category' => 'string'
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'month' => $this->month,
			'day' => $this->day,
			'usual' => $this->usual,
			'country' => $this->country?->getEnglishName(),
			'category' => $this->category->getName()
		];
	}
}
