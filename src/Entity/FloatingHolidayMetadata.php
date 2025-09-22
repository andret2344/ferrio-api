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
class FloatingHolidayMetadata implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private(set) int $id;

	#[ORM\Column(type: 'boolean')]
	private(set) bool $usual;

	#[ORM\ManyToOne(targetEntity: Category::class)]
	#[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
	public ?Category $category;

	#[ORM\ManyToOne(targetEntity: Country::class, inversedBy: 'floatingHolidays')]
	#[ORM\JoinColumn(name: 'country_code', referencedColumnName: 'iso_code', nullable: true)]
	public ?Country $country;

	#[ORM\ManyToOne(targetEntity: Script::class)]
	#[ORM\JoinColumn(name: 'script_id', referencedColumnName: 'id')]
	public ?Script $script;

	#[ORM\Column(type: 'string')]
	private(set) string $args;

	#[ORM\OneToMany(targetEntity: FixedHoliday::class, mappedBy: 'metadata', cascade: ['all'], orphanRemoval: true)]
	private(set) Collection $holidays;

	#[ORM\OneToMany(targetEntity: FloatingHolidayError::class, mappedBy: 'metadata', cascade: ['all'], orphanRemoval: true)]
	private(set) Collection $reports;

	#[ORM\Column(type: 'boolean')]
	private(set) bool $matureContent;

	#[Pure]
	public function __construct(int       $usual,
								?Country  $country,
								?Category $category,
								Script    $script,
								string    $args,
								bool      $matureContent)
	{
		$this->usual = $usual;
		$this->country = $country;
		$this->category = $category;
		$this->script = $script;
		$this->args = $args;
		$this->holidays = new ArrayCollection();
		$this->reports = new ArrayCollection();
		$this->matureContent = $matureContent;
	}

	#[Override]
	#[ArrayShape([
		'id' => 'int',
		'usual' => 'int',
		'category' => 'string',
		'country' => 'string|null',
		'script' => '\App\Entity\Script|null',
		'args' => 'string',
		'mature_content' => 'bool'
	])]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'usual' => $this->usual,
			'category' => $this->category->name,
			'country' => $this->country,
			'script' => $this->script,
			'args' => $this->args,
			'mature_content' => $this->matureContent
		];
	}
}
