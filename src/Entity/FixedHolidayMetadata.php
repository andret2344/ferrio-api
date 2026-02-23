<?php

namespace App\Entity;

use App\Repository\FixedMetadataRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: FixedMetadataRepository::class)]
class FixedHolidayMetadata implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private(set) ?int $id;

	#[ORM\Column(type: 'integer')]
	private(set) int $month;

	#[ORM\Column(type: 'integer')]
	private(set) int $day;

	#[ORM\Column(type: 'boolean')]
	private(set) bool $usual;

	#[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'fixedHolidays')]
	#[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
	public ?Category $category;

	#[ORM\ManyToOne(targetEntity: Country::class, inversedBy: 'fixedHolidays')]
	#[ORM\JoinColumn(name: 'country_code', referencedColumnName: 'iso_code', nullable: true)]
	public ?Country $country;

	#[ORM\OneToMany(targetEntity: FixedHoliday::class, mappedBy: 'metadata', cascade: ['all'], orphanRemoval: true)]
	private(set) Collection $holidays;

	#[ORM\OneToMany(targetEntity: FixedHolidayError::class, mappedBy: 'metadata', cascade: ['all'], orphanRemoval: true)]
	private(set) Collection $reports;

	#[ORM\Column(type: 'boolean')]
	public bool $matureContent;

	public function __construct(int $month, int $day, bool $usual, ?Country $country, ?Category $category, bool $matureContent)
	{
		$this->month = $month;
		$this->day = $day;
		$this->usual = $usual;
		$this->country = $country;
		$this->category = $category;
		$this->holidays = new ArrayCollection();
		$this->reports = new ArrayCollection();
		$this->matureContent = $matureContent;
	}

	#[Override]
	#[ArrayShape([
		'id' => 'int|null',
		'month' => 'int',
		'day' => 'int',
		'usual' => 'bool',
		'country' => 'array|null',
		'category' => 'string|null',
		'mature_content' => 'bool',
	])]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'month' => $this->month,
			'day' => $this->day,
			'usual' => $this->usual,
			'country' => $this->country?->jsonSerialize(),
			'category' => $this->category?->name,
			'mature_content' => $this->matureContent,
		];
	}
}
