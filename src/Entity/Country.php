<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Override;

#[ORM\Entity]
class Country implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\Column(type: 'string', length: 2, unique: true)]
	private(set) string $isoCode;

	#[ORM\Column(type: 'string', length: 255, unique: true)]
	private(set) string $englishName;

	#[ORM\OneToMany(targetEntity: FixedHolidayMetadata::class, mappedBy: 'country', orphanRemoval: true)]
	private(set) Collection $fixedHolidays;

	#[ORM\OneToMany(targetEntity: FloatingHolidayMetadata::class, mappedBy: 'country', orphanRemoval: true)]
	private(set) Collection $floatingHolidays;

	public function __construct(string $isoCode, string $englishName)
	{
		$this->isoCode = $isoCode;
		$this->englishName = $englishName;
		$this->fixedHolidays = new ArrayCollection();
		$this->floatingHolidays = new ArrayCollection();
	}

	public function addFixedHoliday(FixedHolidayMetadata $fixedMetadata): self
	{
		if (!$this->fixedHolidays->contains($fixedMetadata)) {
			$this->fixedHolidays[] = $fixedMetadata;
			$fixedMetadata->country = $this;
		}
		return $this;
	}

	public function removeHoliday(FixedHolidayMetadata $fixedMetadata): self
	{
		if ($this->fixedHolidays->removeElement($fixedMetadata) && $fixedMetadata->country === $this) {
			$fixedMetadata->country = null;
		}
		return $this;
	}

	public function addFloatingHoliday(FloatingHolidayMetadata $floatingMetadata): self
	{
		if (!$this->floatingHolidays->contains($floatingMetadata)) {
			$this->floatingHolidays[] = $floatingMetadata;
			$floatingMetadata->country = $this;
		}
		return $this;
	}

	public function removeFloatingHoliday(FloatingHolidayMetadata $floatingMetadata): self
	{
		if ($this->floatingHolidays->removeElement($floatingMetadata) && $floatingMetadata->country === $this) {
			$floatingMetadata->country = null;
		}
		return $this;
	}

	#[Override]
	#[ArrayShape([
		'isoCode' => 'string',
		'englishName' => 'string'
	])]
	public function jsonSerialize(): array
	{
		return [
			'isoCode' => $this->isoCode,
			'englishName' => $this->englishName
		];
	}
}
