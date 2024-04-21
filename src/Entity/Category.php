<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category implements JsonSerializable {
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer')]
	private int $id;

	#[ORM\Column(type: 'string', length: 63, unique: true)]
	private string $name;

	#[ORM\OneToMany(mappedBy: 'category', targetEntity: FixedHolidayMetadata::class, orphanRemoval: true)]
	private Collection $fixedHolidays;

	#[ORM\OneToMany(mappedBy: 'category', targetEntity: FloatingHolidayMetadata::class, orphanRemoval: true)]
	private Collection $floatingHolidays;

	#[Pure]
	public function __construct(string $id, string $name) {
		$this->id = $id;
		$this->name = $name;
		$this->fixedHolidays = new ArrayCollection();
		$this->floatingHolidays = new ArrayCollection();
	}

	public function getId(): string {
		return $this->id;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getFixedHolidays(): Collection {
		return $this->fixedHolidays;
	}

	public function addFixedHoliday(FixedHolidayMetadata $fixedMetadata): self {
		if (!$this->fixedHolidays->contains($fixedMetadata)) {
			$this->fixedHolidays[] = $fixedMetadata;
			$fixedMetadata->setCategory($this);
		}
		return $this;
	}

	public function removeHoliday(FixedHolidayMetadata $fixedMetadata): self {
		if ($this->fixedHolidays->removeElement($fixedMetadata) && $fixedMetadata->getCategory() === $this) {
			$fixedMetadata->setCategory(null);
		}
		return $this;
	}

	public function getFloatingHolidays(): Collection {
		return $this->floatingHolidays;
	}

	public function addFloatingHoliday(FloatingHolidayMetadata $floatingMetadata): self {
		if (!$this->fixedHolidays->contains($floatingMetadata)) {
			$this->fixedHolidays[] = $floatingMetadata;
			$floatingMetadata->setCategory($this);
		}
		return $this;
	}

	public function removeFloatingHoliday(FloatingHolidayMetadata $floatingMetadata): self {
		if ($this->fixedHolidays->removeElement($floatingMetadata) && $floatingMetadata->getCategory() === $this) {
			$floatingMetadata->setCategory(null);
		}
		return $this;
	}

	#[Pure]
	#[Override]
	#[ArrayShape([
		'id' => 'integer',
		'name' => 'string'
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name
		];
	}
}
