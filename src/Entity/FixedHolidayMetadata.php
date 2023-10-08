<?php

namespace App\Entity;

use App\Repository\FixedMetadataRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;

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
	private int $usual;

	#[ORM\ManyToOne(targetEntity: Category::class)]
	#[Orm\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
	private ?Category $category;

	#[ORM\OneToMany(mappedBy: 'metadata', targetEntity: FixedHoliday::class, cascade: ['all'], orphanRemoval: true)]
	private Collection $holidays;

	#[Pure]
	public function __construct(?int $id, int $month, int $day, int $usual, ?Category $category) {
		$this->id = $id;
		$this->month = $month;
		$this->day = $day;
		$this->usual = $usual;
		$this->category = $category;
		$this->holidays = new ArrayCollection();
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

	public function addHoliday(FixedHoliday $holiday1): self {
		if (!$this->holidays->contains($holiday1)) {
			$this->holidays[] = $holiday1;
			$holiday1->setMetadata($this);
		}
		return $this;
	}

	public function removeHoliday(FixedHoliday $holiday1): self {
		if ($this->holidays->removeElement($holiday1) && $holiday1->getMetadata() === $this) {
			$holiday1->setMetadata(null);
		}
		return $this;
	}

	#[ArrayShape([
		'id' => 'int|null',
		'month' => 'int',
		'day' => 'int',
		'usual' => 'int'
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'month' => $this->month,
			'day' => $this->day,
			'usual' => $this->usual
		];
	}
}
