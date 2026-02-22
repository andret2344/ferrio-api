<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Override;

#[ORM\Entity]
class Script implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer')]
	private(set) int $id;

	#[ORM\Column(type: 'text')]
	public string $content;

	#[ORM\OneToMany(targetEntity: FloatingHolidayMetadata::class, mappedBy: 'script', cascade: ['all'], orphanRemoval: true)]
	private(set) Collection $metadata;

	public function __construct(int $id, string $content)
	{
		$this->id = $id;
		$this->content = $content;
		$this->metadata = new ArrayCollection();
	}

	public function addMetadata(FloatingHolidayMetadata $metadata): self
	{
		if (!$this->metadata->contains($metadata)) {
			$this->metadata[] = $metadata;
			$metadata->script = $this;
		}
		return $this;
	}

	public function removeMetadata(FloatingHolidayMetadata $metadata): self
	{
		if ($this->metadata->removeElement($metadata) && $metadata->script === $this) {
			$metadata->script = null;
		}
		return $this;
	}

	#[Override]
	#[ArrayShape([
		'id' => 'integer',
		'content' => 'string',
	])]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'content' => $this->content,
		];
	}
}
