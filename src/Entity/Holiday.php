<?php

namespace App\Entity;

use App\Repository\HolidayRepository;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;

#[ORM\Entity(repositoryClass: HolidayRepository::class)]
class Holiday implements JsonSerializable {
	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: Language::class)]
	#[Orm\JoinColumn(name: 'language_code', referencedColumnName: 'code', nullable: false)]
	private Language $language;

	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: HolidayMetadata::class)]
	#[Orm\JoinColumn(name: 'metadata_id', referencedColumnName: 'id', nullable: false)]
	private HolidayMetadata $metadata;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $name;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $description;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $url;

	public function __construct(Language $language, HolidayMetadata $metadata, ?string $name,
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

	public function getUrl(): ?string {
		return $this->url;
	}

	public function setUrl(?string $url): void {
		$this->url = $url;
	}

	#[ArrayShape([
		'id' => 'int',
		'usual' => 'boolean',
		'name' => 'null|string',
		'description' => 'null|string',
		'link' => 'null|string',
		'url' => 'null|string'
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->metadata->getId(),
			'usual' => (bool)$this->metadata->getUsual(),
			'name' => $this->name,
			'description' => $this->description,
			'link' => $this->url,
			'url' => $this->url
		];
	}
}
