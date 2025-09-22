<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Override;

#[ORM\Entity]
class FloatingHoliday implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: Language::class)]
	#[ORM\JoinColumn(name: 'language_code', referencedColumnName: 'code')]
	private(set) Language $language;

	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: FloatingHolidayMetadata::class, inversedBy: 'holidays')]
	#[ORM\JoinColumn(name: 'metadata_id', referencedColumnName: 'id')]
	private(set) FloatingHolidayMetadata $metadata;

	#[ORM\Column(type: 'text')]
	private(set) ?string $name;

	#[ORM\Column(type: 'text')]
	private(set) ?string $description;

	#[ORM\Column(type: 'text')]
	private(set) ?string $url;

	public function __construct(Language $language, FloatingHolidayMetadata $metadata,
								?string  $name, ?string $description, ?string $url)
	{
		$this->language = $language;
		$this->metadata = $metadata;
		$this->name = $name;
		$this->description = $description;
		$this->url = $url;
	}

	#[Override]
	#[ArrayShape([
		'id' => "int",
		'usual' => "bool",
		'name' => "null|string",
		'description' => "null|string",
		'url' => "null|string",
		'script' => "null|string"
	])]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->metadata->id,
			'usual' => (bool)$this->metadata,
			'name' => $this->name,
			'description' => $this->description,
			'url' => $this->url,
			'script' => $this->metadata->script->content
		];
	}
}
