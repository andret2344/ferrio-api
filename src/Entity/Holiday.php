<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

#[ORM\Entity]
class Holiday implements JsonSerializable {
	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: Language::class, cascade: ['persist'], inversedBy: 'holidays')]
	#[ORM\JoinColumn(referencedColumnName: 'code', nullable: false)]
	private Language $language;

	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: HolidayMetadata::class, cascade: ['persist'], inversedBy: 'holidays')]
	#[ORM\JoinColumn(referencedColumnName: 'id', nullable: false)]
	private HolidayMetadata $metadata;

	#[ORM\Column(type: "text", nullable: true)]
	private ?string $name;

	#[ORM\Column(type: "text", nullable: true)]
	private ?string $description;

	#[ORM\Column(type: "text", nullable: true)]
	private ?string $link;

	public function __construct(Language $language, HolidayMetadata $metadata, ?string $name, ?string $description, ?string $link) {
		$this->language = $language;
		$this->metadata = $metadata;
		$this->name = $name;
		$this->description = $description;
		$this->link = $link;
	}

	public function getLanguage(): ?Language {
		return $this->language;
	}

	public function setLanguage(?Language $language): self {
		$this->language = $language;
		return $this;
	}

	public function getMetadata(): ?HolidayMetadata {
		return $this->metadata;
	}

	public function setMetadata(?HolidayMetadata $holidayMetadata): self {
		$this->metadata = $holidayMetadata;
		return $this;
	}

	public function getName(): ?string {
		return $this->name;
	}

	public function setName(?string $name): void {
		$this->name = $name;
	}

	public function getDescription(): ?string {
		return $this->description;
	}

	public function setDescription(?string $description): void {
		$this->description = $description;
	}

	public function getLink(): ?string {
		return $this->link;
	}

	public function setLink(?string $link): void {
		$this->link = $link;
	}

	public function jsonSerialize(): array {
		return [
			'metadata' => $this->metadata,
			'name' => $this->name,
			'description' => $this->description,
			'link' => $this->link
		];
	}
}
