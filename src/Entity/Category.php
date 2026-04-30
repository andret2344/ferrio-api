<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer')]
	private(set) ?int $id = null;

	#[ORM\Column(type: 'string', length: 63, unique: true)]
	private(set) string $slug;

	#[ORM\OneToMany(targetEntity: CategoryTranslation::class, mappedBy: 'category', cascade: ['all'], orphanRemoval: true)]
	private(set) Collection $translations;

	#[ORM\ManyToMany(targetEntity: FixedHolidayMetadata::class, mappedBy: 'categories')]
	private(set) Collection $fixedHolidays;

	#[ORM\ManyToMany(targetEntity: FloatingHolidayMetadata::class, mappedBy: 'categories')]
	private(set) Collection $floatingHolidays;

	public function __construct(string $slug)
	{
		$this->slug = $slug;
		$this->translations = new ArrayCollection();
		$this->fixedHolidays = new ArrayCollection();
		$this->floatingHolidays = new ArrayCollection();
	}

	public function nameFor(string $languageCode): ?string
	{
		foreach ($this->translations as $translation) {
			if ($translation->language->code === $languageCode) {
				return $translation->name;
			}
		}
		return null;
	}

	#[Override]
	#[ArrayShape([
		'id' => 'int|null',
		'slug' => 'string'
	])]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'slug' => $this->slug
		];
	}
}
