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
class Language implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\Column(type: 'string', length: 31)]
	private(set) string $code;

	#[ORM\Column(type: 'string', length: 63, unique: true)]
	private(set) string $name;

	#[ORM\OneToMany(targetEntity: FixedHoliday::class, mappedBy: 'language', orphanRemoval: true)]
	private(set) Collection $holidays;

	#[Pure]
	public function __construct(string $code, string $name)
	{
		$this->code = $code;
		$this->name = $name;
		$this->holidays = new ArrayCollection();
	}

	public function addHoliday(FixedHoliday $holiday): self
	{
		if (!$this->holidays->contains($holiday)) {
			$this->holidays[] = $holiday;
			$holiday->setLanguage($this);
		}
		return $this;
	}

	public function removeHoliday(FixedHoliday $holiday): self
	{
		if ($this->holidays->removeElement($holiday) && $holiday->getLanguage() === $this) {
			$holiday->setLanguage(null);
		}
		return $this;
	}

	#[Pure]
	#[Override]
	#[ArrayShape([
		'code' => 'string',
		'name' => 'string'
	])]
	public function jsonSerialize(): array
	{
		return [
			'code' => $this->code,
			'name' => $this->name
		];
	}
}
