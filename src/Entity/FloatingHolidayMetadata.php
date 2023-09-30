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
	private int $usual;

	#[ORM\ManyToOne(targetEntity: Script::class)]
	#[Orm\JoinColumn(name: 'script_id', referencedColumnName: 'id', nullable: false)]
	private ?Script $script;

	#[ORM\Column(type: 'string')]
	private string $args;

	#[ORM\OneToMany(mappedBy: 'metadata', targetEntity: FixedHoliday::class, cascade: ['all'], orphanRemoval: true)]
	private Collection $holidays;

	#[Pure]
	public function __construct(int $id, int $usual, ?Script $script, string $args) {
		$this->id = $id;
		$this->usual = $usual;
		$this->script = $script;
		$this->args = $args;
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

	public function getScript(): ?Script {
		return $this->script;
	}

	public function setScript(?Script $script): self {
		$this->script = $script;
		return $this;
	}

	#[ArrayShape([
		'id' => "int",
		'usual' => "int",
		'script' => "\App\Entity\Script|null",
		'args' => "string"
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'usual' => $this->usual,
			'script' => $this->script,
			'args' => $this->args
		];
	}
}
