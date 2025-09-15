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
class Script implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer', nullable: false)]
	private(set) int $id;

	#[ORM\Column(type: 'text', nullable: false)]
	public string $content;

	#[ORM\OneToMany(targetEntity: FixedHoliday::class, mappedBy: 'metadata', cascade: ['all'], orphanRemoval: true)]
	private(set) Collection $metadata;

	#[Pure]
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

	#[Pure]
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
