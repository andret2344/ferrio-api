<?php

namespace App\Entity;

use App\Enum\Algorithm;
use App\Repository\FloatingMetadataRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: FloatingMetadataRepository::class)]
class FloatingHolidayMetadata implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private(set) ?int $id;

	#[ORM\Column(type: 'boolean')]
	private(set) bool $usual;

	#[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'floatingHolidays')]
	#[ORM\JoinTable(name: 'floating_holiday_metadata_category')]
	#[ORM\JoinColumn(name: 'metadata_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
	#[ORM\InverseJoinColumn(name: 'category_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
	public Collection $categories;

	#[ORM\ManyToOne(targetEntity: Country::class, inversedBy: 'floatingHolidays')]
	#[ORM\JoinColumn(name: 'country_code', referencedColumnName: 'iso_code', nullable: true)]
	public ?Country $country;

	#[ORM\ManyToOne(targetEntity: Script::class)]
	#[ORM\JoinColumn(name: 'script_id', referencedColumnName: 'id')]
	public ?Script $script;

	#[ORM\Column(type: 'string')]
	private(set) string $args;

	#[ORM\Column(type: 'string', nullable: true)]
	public ?string $algorithmArgs;

	#[ORM\OneToMany(targetEntity: FloatingHoliday::class, mappedBy: 'metadata', cascade: ['all'], orphanRemoval: true)]
	private(set) Collection $holidays;

	#[ORM\OneToMany(targetEntity: FloatingHolidayError::class, mappedBy: 'metadata', cascade: ['all'], orphanRemoval: true)]
	private(set) Collection $reports;

	#[ORM\Column(type: 'string', enumType: Algorithm::class)]
	public Algorithm $algorithm;

	#[ORM\Column(type: 'boolean')]
	public bool $matureContent;

	/**
	 * @param Category[] $categories
	 */
	public function __construct(
		bool      $usual,
		?Country  $country,
		array     $categories,
		?Script   $script,
		string    $args,
		bool      $matureContent,
		Algorithm $algorithm,
		?string   $algorithmArgs = null)
	{
		$this->usual = $usual;
		$this->country = $country;
		$this->categories = new ArrayCollection($categories);
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
			'categories' => $this->categories->map(fn(Category $c) => $c->slug)
				->getValues(),
			'country' => $this->country?->jsonSerialize(),
			'script' => $this->script,
			'args' => $this->args,
			'algorithm_args' => $this->algorithmArgs,
			'algorithm' => $this->algorithm->value,
			'mature_content' => $this->matureContent
		];
	}
}
