<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Override;

#[ORM\Entity]
class Category implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer')]
	private(set) int $id;

	#[ORM\Column(type: 'string', length: 63, unique: true)]
	private(set) string $name;

	#[ORM\OneToMany(targetEntity: FixedHolidayMetadata::class, mappedBy: 'category', orphanRemoval: true)]
	private(set) Collection $fixedHolidays;

	#[ORM\OneToMany(targetEntity: FloatingHolidayMetadata::class, mappedBy: 'category', orphanRemoval: true)]
	private(set) Collection $floatingHolidays;

	#[Pure]
	public function __construct(string $id, string $name)
	{
		$this->id = $id;
		$this->name = $name;
		$this->fixedHolidays = new ArrayCollection();
		$this->floatingHolidays = new ArrayCollection();
	}

	public function addFixedHoliday(FixedHolidayMetadata $fixedMetadata): self
	{
		if (!$this->fixedHolidays->contains($fixedMetadata)) {
			$this->fixedHolidays[] = $fixedMetadata;
			$fixedMetadata->category = $this;
		}
		return $this;
	}

	public function removeHoliday(FixedHolidayMetadata $fixedMetadata): self
	{
		if ($this->fixedHolidays->removeElement($fixedMetadata) && $fixedMetadata->category === $this) {
			$fixedMetadata->category = null;
		}
		return $this;
	}

	public function addFloatingHoliday(FloatingHolidayMetadata $floatingMetadata): self
	{
		if (!$this->fixedHolidays->contains($floatingMetadata)) {
			$this->fixedHolidays[] = $floatingMetadata;
			$floatingMetadata->category = $this;
		}
		return $this;
	}

	public function removeFloatingHoliday(FloatingHolidayMetadata $floatingMetadata): self
	{
		if ($this->fixedHolidays->removeElement($floatingMetadata) && $floatingMetadata->category === $this) {
			$floatingMetadata->category = null;
		}
		return $this;
	}

	#[Pure]
	#[Override]
	#[ArrayShape([
		'id' => 'integer',
		'name' => 'string'
	])]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'name' => $this->name
		];
	}
}
