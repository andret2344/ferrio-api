<?php

namespace App\Entity;

use App\Repository\FixedHolidayRepository;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: FixedHolidayRepository::class)]
class FixedHoliday implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: Language::class, inversedBy: 'holidays')]
	#[ORM\JoinColumn(name: 'language_code', referencedColumnName: 'code')]
	private(set) Language $language;

	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: FixedHolidayMetadata::class, inversedBy: 'holidays')]
	#[ORM\JoinColumn(name: 'metadata_id', referencedColumnName: 'id')]
	private(set) FixedHolidayMetadata $metadata;

	#[ORM\Column(type: 'text')]
	public string $name;

	#[ORM\Column(type: 'text', nullable: true)]
	public ?string $description;

	#[ORM\Column(type: 'text', nullable: true)]
	private(set) ?string $url;

	public function __construct(Language $language, FixedHolidayMetadata $metadata, string $name,
								?string  $description = null, ?string $url = null)
	{
		$this->language = $language;
		$this->metadata = $metadata;
		$this->name = $name;
		$this->description = $description;
		$this->url = $url;
	}

	#[Override]
	#[ArrayShape([
		'id' => 'int',
		'usual' => 'boolean',
		'name' => 'null|string',
		'description' => 'null|string',
		'url' => 'null|string'
	])]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->metadata->id,
			'usual' => $this->metadata->usual,
			'name' => $this->name,
			'description' => $this->description,
			'url' => $this->url
		];
	}
}
