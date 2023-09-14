<?php

namespace App\Entity;

use App\Repository\MetadataRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;

#[ORM\Entity(repositoryClass: MetadataRepository::class)]
class FloatingHolidayMetadata implements JsonSerializable {
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	private int $id;

	#[ORM\Column(type: 'boolean')]
	private int $usual;

	#[ORM\OneToMany(mappedBy: 'metadata', targetEntity: Holiday::class, orphanRemoval: true)]
	private Collection $holidays;

	#[Pure]
	public function __construct(int $id, int $usual) {
		$this->id = $id;
		$this->usual = $usual;
		$this->holidays = new ArrayCollection();
	}

	public function getId(): int {
		return $this->id;
	}

	public function getUsual(): int {
		return $this->usual;
	}

	public function getHolidays(): Collection {
		return $this->holidays;
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

	#[ArrayShape([
		'id' => 'int',
		'usual' => 'int'
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'usual' => $this->usual
		];
	}
}
