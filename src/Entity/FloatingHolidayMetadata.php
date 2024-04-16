<?php

namespace App\Entity;

use App\Repository\FloatingMetadataRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;

#[ORM\Entity(repositoryClass: FloatingMetadataRepository::class)]
class FloatingHolidayMetadata implements JsonSerializable {
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private int $id;

	#[ORM\Column(type: 'boolean')]
	private bool $usual;

	#[ORM\ManyToOne(targetEntity: Category::class)]
	#[Orm\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
	private ?Category $category;

	#[ORM\ManyToOne(targetEntity: Country::class, inversedBy: 'floatingHolidays')]
	#[Orm\JoinColumn(name: 'country_code', referencedColumnName: 'iso_code', nullable: true)]
	private ?Country $country;

	#[ORM\ManyToOne(targetEntity: Script::class)]
	#[Orm\JoinColumn(name: 'script_id', referencedColumnName: 'id', nullable: false)]
	private ?Script $script;

	#[ORM\Column(type: 'string')]
	private string $args;

	#[ORM\OneToMany(mappedBy: 'metadata', targetEntity: FixedHoliday::class, cascade: ['all'], orphanRemoval: true)]
	private Collection $holidays;

	#[ORM\OneToMany(mappedBy: 'metadata', targetEntity: FixedHolidayReport::class, cascade: ['all'], orphanRemoval: true)]
	private Collection $reports;

	#[Pure]
	public function __construct(int       $id,
								int       $usual,
								?Country  $country,
								?Category $category,
								?Script   $script,
								string    $args) {
		$this->id = $id;
		$this->usual = $usual;
		$this->country = $country;
		$this->category = $category;
		$this->script = $script;
		$this->args = $args;
		$this->holidays = new ArrayCollection();
		$this->reports = new ArrayCollection();
	}

	public function getId(): int {
		return $this->id;
	}

	public function getUsual(): int {
		return $this->usual;
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

	public function getHolidays(): Collection {
		return $this->holidays;
	}

	public function getArgs(): string {
		return $this->args;
	}

	public function addHoliday(FloatingHoliday $holiday): self {
		if (!$this->holidays->contains($holiday)) {
			$this->holidays[] = $holiday;
			$holiday->setMetadata($this);
		}
		return $this;
	}

	public function removeHoliday(FloatingHoliday $holiday): self {
		if ($this->holidays->removeElement($holiday) && $holiday->getMetadata() === $this) {
			$holiday->setMetadata(null);
		}
		return $this;
	}

	public function addReport(FloatingHolidayReport $report): self {
		if (!$this->reports->contains($report)) {
			$this->reports[] = $report;
			$report->setMetadata($this);
		}
		return $this;
	}

	public function removeReport(FloatingHolidayReport $report): self {
		if ($this->holidays->removeElement($report) && $report->getMetadata() === $this) {
			$report->setMetadata(null);
		}
		return $this;
	}

	public function getScript(): ?Script {
		return $this->script;
	}

	public function setScript(?Script $script): self {
		$this->script = $script;
		return $this;
	}

	#[ArrayShape([
		'id' => 'int',
		'usual' => 'int',
		'category' => 'string',
		'country' => 'string|null',
		'script' => '\App\Entity\Script|null',
		'args' => 'string'
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'usual' => $this->usual,
			'category' => $this->category->getName(),
			'country' => $this->country?->getEnglishName(),
			'script' => $this->script,
			'args' => $this->args
		];
	}
}
