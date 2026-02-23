<?php

namespace App\Entity;

use App\Enum\Algorithm;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Override;

#[ORM\Entity]
class FloatingHolidayMetadata implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private(set) ?int $id;

	#[ORM\Column(type: 'boolean')]
	private(set) bool $usual;

	#[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'floatingHolidays')]
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

	#[ORM\Column(type: 'string', nullable: true)]
	private(set) ?string $algorithmArgs;

	#[ORM\OneToMany(targetEntity: FloatingHoliday::class, mappedBy: 'metadata', cascade: ['all'], orphanRemoval: true)]
	private(set) Collection $holidays;

	#[ORM\OneToMany(targetEntity: FloatingHolidayError::class, mappedBy: 'metadata', cascade: ['all'], orphanRemoval: true)]
	private(set) Collection $reports;

	#[ORM\Column(type: 'string', enumType: Algorithm::class)]
	private(set) Algorithm $algorithm;

	#[ORM\Column(type: 'boolean')]
	private(set) bool $matureContent;

	public function __construct(bool      $usual,
								?Country  $country,
								?Category $category,
								Script    $script,
								string    $args,
								bool      $matureContent,
								Algorithm $algorithm,
								?string   $algorithmArgs = null)
	{
		$this->usual = $usual;
		$this->country = $country;
		$this->category = $category;
		$this->script = $script;
		$this->args = $args;
		$this->holidays = new ArrayCollection();
		$this->reports = new ArrayCollection();
		$this->matureContent = $matureContent;
		$this->algorithm = $algorithm;
		$this->algorithmArgs = $algorithmArgs;
	}

	#[Override]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'usual' => $this->usual,
			'category' => $this->category?->name,
			'country' => $this->country?->jsonSerialize(),
			'script' => $this->script,
			'args' => $this->args,
			'algorithm_args' => $this->algorithmArgs,
			'algorithm' => $this->algorithm->value,
			'mature_content' => $this->matureContent
		];
	}
}
