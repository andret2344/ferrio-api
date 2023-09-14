<?php

namespace App\Entity;

use App\Repository\FloatingHolidayRepository;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;

#[ORM\Entity(repositoryClass: FloatingHolidayRepository::class)]
class FloatingHoliday implements JsonSerializable {
	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: Language::class)]
	#[Orm\JoinColumn(name: 'language_code', referencedColumnName: 'code', nullable: false)]
	private Language $language;

	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: FloatingHolidayMetadata::class)]
	#[Orm\JoinColumn(name: 'metadata_id', referencedColumnName: 'id', nullable: false)]
	private FloatingHolidayMetadata $metadata;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $name;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $description;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $url;

	public function __construct(Language $language, FloatingHolidayMetadata $metadata,
								?string  $name, ?string $description, ?string $url) {
		$this->language = $language;
		$this->metadata = $metadata;
		$this->name = $name;
		$this->description = $description;
		$this->url = $url;
	}


	public function getLanguage(): Language {
		return $this->language;
	}

	public function setLanguage(Language $language): self {
		$this->language = $language;
		return $this;
	}

	public function getMetadata(): ?FloatingHolidayMetadata {
		return $this->metadata;
	}

	public function setMetadata(?FloatingHolidayMetadata $metadata): self {
		$this->metadata = $metadata;
		return $this;
	}

	public function getName(): ?string {
		return $this->name;
	}

	public function setName(?string $name): self {
		$this->name = $name;
		return $this;
	}

	public function getDescription(): ?string {
		return $this->description;
	}

	public function setDescription(?string $description): self {
		$this->description = $description;
		return $this;
	}

	public function getUrl(): ?string {
		return $this->url;
	}

	public function setUrl(?string $url): self {
		$this->url = $url;
		return $this;
	}

	#[ArrayShape([
		'id' => "int",
		'usual' => "bool",
		'name' => "null|string",
		'description' => "null|string",
		'url' => "null|string",
		'script' => "null|string"
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->metadata->getId(),
			'usual' => (bool)$this->metadata->getUsual(),
			'name' => $this->name,
			'description' => $this->description,
			'url' => $this->url,
			'script' => $this->metadata->getScript()
		];
	}
}
