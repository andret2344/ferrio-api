<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;

#[ORM\Entity]
class Language implements JsonSerializable {
	#[ORM\Id]
	#[ORM\Column(type: 'string', length: 31)]
	private string $code;

	#[ORM\Column(type: 'string', length: 63)]
	private string $name;

	#[ORM\Column(type: 'integer')]
	private int $releaseId;

	#[ORM\OneToMany(mappedBy: 'language', targetEntity: Holiday::class, orphanRemoval: true)]
	private Collection $holidays;

	#[Pure]
	public function __construct(string $code, string $name, string $releaseId) {
		$this->code = $code;
		$this->name = $name;
		$this->releaseId = $releaseId;
		$this->holidays = new ArrayCollection();
	}

	public function getCode(): string {
		return $this->code;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getReleaseId(): int|string {
		return $this->releaseId;
	}

	public function getHolidays(): Collection {
		return $this->holidays;
	}

	public function addHoliday(Holiday $holiday): self {
		if (!$this->holidays->contains($holiday)) {
			$this->holidays[] = $holiday;
			$holiday->setLanguage($this);
		}

		return $this;
	}

	public function removeHoliday(Holiday $holiday): self {
		if ($this->holidays->removeElement($holiday) && $holiday->getLanguage() === $this) {
			$holiday->setLanguage(null);
		}

		return $this;
	}

	#[ArrayShape([
		'uniLanguage' => "string",
		'language' => "string",
		'code' => "string",
		'name' => "string",
		'releaseId' => "int|string"
	])]
	public function jsonSerialize(): array {
		return [
			'uniLanguage' => $this->code,
			'language' => $this->name,
			'code' => $this->code,
			'name' => $this->name,
			'releaseId' => $this->releaseId
		];
	}
}
