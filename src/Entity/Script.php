<?php

namespace App\Entity;

use App\Repository\ScriptRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: ScriptRepository::class)]
class Script implements JsonSerializable {
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer', nullable: false)]
	private string $id;

	#[ORM\Column(type: 'text', nullable: false)]
	private string $content;

	#[ORM\OneToMany(targetEntity: FixedHoliday::class, mappedBy: 'metadata', cascade: ['all'], orphanRemoval: true)]
	private Collection $metadata;

	#[Pure]
	public function __construct(string $id, string $content) {
		$this->id = $id;
		$this->content = $content;
		$this->metadata = new ArrayCollection();
	}

	public function getId(): string {
		return $this->id;
	}

	public function getContent(): string {
		return $this->content;
	}

	public function setContent(string $content): void {
		$this->content = $content;
	}

	public function getMetadata(): Collection {
		return $this->metadata;
	}

	public function addMetadata(FloatingHolidayMetadata $metadata): self {
		if (!$this->metadata->contains($metadata)) {
			$this->metadata[] = $metadata;
			$metadata->setScript($this);
		}
		return $this;
	}

	public function removeMetadata(FloatingHolidayMetadata $metadata): self {
		if ($this->metadata->removeElement($metadata) && $metadata->getScript() === $this) {
			$metadata->setScript(null);
		}
		return $this;
	}

	#[Pure]
	#[Override]
	#[ArrayShape([
		'id' => 'integer',
		'content' => 'string',
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'content' => $this->content,
		];
	}
}
