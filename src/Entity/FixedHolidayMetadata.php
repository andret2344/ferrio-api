<?php

namespace App\Entity;

use App\Repository\FixedMetadataRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FixedMetadataRepository::class)]
class FixedHolidayMetadata
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private(set) ?int $id;

	#[ORM\Column(type: 'integer')]
	public int $month;

	#[ORM\Column(type: 'integer')]
	public int $day;

	#[ORM\Column(type: 'boolean')]
	private(set) bool $usual;

	#[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'fixedHolidays')]
	#[ORM\JoinTable(name: 'fixed_holiday_metadata_category')]
	#[ORM\JoinColumn(name: 'metadata_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
	#[ORM\InverseJoinColumn(name: 'category_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
	public Collection $categories;

	#[ORM\ManyToOne(targetEntity: Country::class)]
	#[ORM\JoinColumn(name: 'country_code', referencedColumnName: 'iso_code', nullable: true)]
	public ?Country $country;

	#[ORM\OneToMany(targetEntity: FixedHoliday::class, mappedBy: 'metadata', cascade: ['all'], orphanRemoval: true)]
	private(set) Collection $holidays;

	#[ORM\OneToMany(targetEntity: FixedHolidayError::class, mappedBy: 'metadata', cascade: ['all'], orphanRemoval: true)]
	private(set) Collection $reports;

	#[ORM\Column(type: 'boolean')]
	public bool $matureContent;

	/**
	 * @param Category[] $categories
	 */
	public function __construct(int $month, int $day, bool $usual, ?Country $country, array $categories, bool $matureContent)
	{
		$this->month = $month;
		$this->day = $day;
		$this->usual = $usual;
		$this->country = $country;
		$this->categories = new ArrayCollection($categories);
		$this->holidays = new ArrayCollection();
		$this->reports = new ArrayCollection();
		$this->matureContent = $matureContent;
	}
}
