<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;

#[ORM\Entity]
class Holiday implements JsonSerializable {
	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: Language::class)]
	#[Orm\JoinColumn(name: 'language_code', referencedColumnName: 'code', nullable: false)]
	private Language $language;

	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: HolidayMetadata::class)]
	#[Orm\JoinColumn(name: 'metadata_id', referencedColumnName: 'id', nullable: false)]
	private HolidayMetadata $holidayMetadata;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $name;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $description;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $link;

	public function __construct(Language $language, HolidayMetadata $holidayMetadata, ?string $name, ?string $description, ?string $link) {
		$this->language = $language;
		$this->holidayMetadata = $holidayMetadata;
		$this->name = $name;
		$this->description = $description;
		$this->link = $link;
	}

	public function getLanguage(): Language {
		return $this->language;
	}

	public function getHolidayMetadata(): HolidayMetadata {
		return $this->holidayMetadata;
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

	#[ArrayShape([
		'id' => 'int',
		'usual' => 'boolean',
		'name' => 'null|string',
		'description' => 'null|string',
		'link' => 'null|string'
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->holidayMetadata->getId(),
			'usual' => $this->holidayMetadata->getUsual(),
			'name' => $this->name,
			'description' => $this->description,
			'link' => $this->link
		];
	}
}
