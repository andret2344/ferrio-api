<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Override;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[ORM\Entity]
class Country extends AbstractController implements JsonSerializable {
	#[ORM\Id]
	#[ORM\Column(type: 'string', length: 2, unique: true)]
	private string $isoCode;

	#[ORM\Column(type: 'string', length: 255, unique: true)]
	private string $englishName;

	#[ORM\OneToMany(mappedBy: 'country', targetEntity: FixedHolidayMetadata::class, orphanRemoval: true)]
	private Collection $fixedHolidays;

	#[ORM\OneToMany(mappedBy: 'country', targetEntity: FloatingHolidayMetadata::class, orphanRemoval: true)]
	private Collection $floatingHolidays;

	public function __construct(string $isoCode, string $englishName) {
		$this->isoCode = $isoCode;
		$this->englishName = $englishName;
		$this->fixedHolidays = new ArrayCollection();
		$this->floatingHolidays = new ArrayCollection();
	}

	public function getIsoCode(): string {
		return $this->isoCode;
	}

	public function setIsoCode(string $isoCode): void {
		$this->isoCode = $isoCode;
	}

	public function getEnglishName(): string {
		return $this->englishName;
	}

	public function setEnglishName(string $englishName): void {
		$this->englishName = $englishName;
	}

	public function addFixedHoliday(FixedHolidayMetadata $fixedMetadata): self {
		if (!$this->fixedHolidays->contains($fixedMetadata)) {
			$this->fixedHolidays[] = $fixedMetadata;
			$fixedMetadata->setCountry($this);
		}
		return $this;
	}

	public function removeHoliday(FixedHolidayMetadata $fixedMetadata): self {
		if ($this->fixedHolidays->removeElement($fixedMetadata) && $fixedMetadata->getCountry() === $this) {
			$fixedMetadata->setCountry(null);
		}
		return $this;
	}

	public function getFloatingHolidays(): Collection {
		return $this->floatingHolidays;
	}

	public function addFloatingHoliday(FloatingHolidayMetadata $floatingMetadata): self {
		if (!$this->fixedHolidays->contains($floatingMetadata)) {
			$this->fixedHolidays[] = $floatingMetadata;
			$floatingMetadata->setCountry($this);
		}
		return $this;
	}

	public function removeFloatingHoliday(FloatingHolidayMetadata $floatingMetadata): self {
		if ($this->fixedHolidays->removeElement($floatingMetadata) && $floatingMetadata->getCountry() === $this) {
			$floatingMetadata->setCountry(null);
		}
		return $this;
	}

	#[Pure]
	#[Override]
	#[ArrayShape([
		'isoCode' => 'string',
		'englishName' => 'string'
	])]
	public function jsonSerialize(): array {
		return [
			'isoCode' => $this->isoCode,
			'englishName' => $this->englishName
		];
	}
}
