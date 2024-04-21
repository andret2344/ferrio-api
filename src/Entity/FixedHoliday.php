<?php

namespace App\Entity;

use App\Repository\FixedHolidayRepository;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: FixedHolidayRepository::class)]
class FixedHoliday implements JsonSerializable {
	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: Language::class, inversedBy: 'holidays')]
	#[ORM\JoinColumn(name: 'language_code', referencedColumnName: 'code', nullable: false)]
	private Language $language;

	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: FixedHolidayMetadata::class, inversedBy: 'holidays')]
	#[ORM\JoinColumn(name: 'metadata_id', referencedColumnName: 'id', nullable: false)]
	private FixedHolidayMetadata $metadata;

	#[ORM\Column(type: 'text', nullable: false)]
	private ?string $name;

	#[ORM\Column(type: 'text', nullable: false)]
	private ?string $description;

	#[ORM\Column(type: 'text', nullable: false)]
	private ?string $url;

	public function __construct(Language $language, FixedHolidayMetadata $metadata, ?string $name,
								?string  $description, ?string $url) {
		$this->language = $language;
		$this->metadata = $metadata;
		$this->name = $name;
		$this->description = $description;
		$this->url = $url;
	}

	public function getLanguage(): ?Language {
		return $this->language;
	}

	public function setLanguage(?Language $language): static {
		$this->language = $language;
		return $this;
	}

	public function getMetadata(): ?FixedHolidayMetadata {
		return $this->metadata;
	}

	public function setMetadata(?FixedHolidayMetadata $holidayMetadata): static {
		$this->metadata = $holidayMetadata;
		return $this;
	}

	public function getName(): ?string {
		return $this->name;
	}

	public function setName(?string $name): static {
		$this->name = $name;
		return $this;
	}

	public function getDescription(): ?string {
		return $this->description;
	}

	public function setDescription(?string $description): static {
		$this->description = $description;
		return $this;
	}

	public function getUrl(): ?string {
		return $this->url;
	}

	public function setUrl(?string $url): static {
		$this->url = $url;
		return $this;
	}

	#[Pure]
	#[Override]
	#[ArrayShape([
		'id' => 'int',
		'usual' => 'boolean',
		'name' => 'null|string',
		'description' => 'null|string',
		'url' => 'null|string'
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->metadata->getId(),
			'usual' => (bool)$this->metadata->getUsual(),
			'name' => $this->name,
			'description' => $this->description,
			'url' => $this->url
		];
	}
}
