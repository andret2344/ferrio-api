<?php

namespace App\Entity;

use App\Repository\FixedMetadataRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
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

	#[ORM\ManyToOne(targetEntity: Category::class)]
	#[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
	public ?Category $category;

	#[ORM\ManyToOne(targetEntity: Country::class, inversedBy: 'fixedHolidays')]
	#[ORM\JoinColumn(name: 'country_code', referencedColumnName: 'iso_code', nullable: true)]
	public ?Country $country;

	#[ORM\OneToMany(targetEntity: FixedHoliday::class, mappedBy: 'metadata', cascade: ['all'], orphanRemoval: true)]
	private Collection $holidays;

	#[ORM\OneToMany(targetEntity: FixedHolidayError::class, mappedBy: 'metadata', cascade: ['all'], orphanRemoval: true)]
	private Collection $reports;

	#[ORM\Column(type: 'boolean')]
	public bool $matureContent;

	#[Pure]
	public function __construct(int $month, int $day, int $usual, ?Country $country, ?Category $category, bool $matureContent)
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

	public function addHoliday(FixedHoliday $holiday): self
	{
		if (!$this->holidays->contains($holiday)) {
			$this->holidays[] = $holiday;
			$holiday->setMetadata($this);
		}
		return $this;
	}

	public function removeHoliday(FixedHoliday $holiday): self
	{
		if ($this->holidays->removeElement($holiday) && $holiday->getMetadata() === $this) {
			$holiday->setMetadata(null);
		}
		return $this;
	}

	public function addReport(FixedHolidayError $report): self
	{
		if (!$this->reports->contains($report)) {
			$this->reports[] = $report;
			$report->setMetadata($this);
		}
		return $this;
	}

	public function removeReport(FixedHolidayError $report): self
	{
		if ($this->reports->removeElement($report) && $report->getMetadata() === $this) {
			$report->setMetadata(null);
		}
		return $this;
	}

	#[Override]
	#[ArrayShape([
		'id' => 'int|null',
		'month' => 'int',
		'day' => 'int',
		'usual' => 'int',
		'country' => 'string|null',
		'category' => 'string',
		'mature_content' => 'bool',
	])]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'month' => $this->month,
			'day' => $this->day,
			'usual' => $this->usual,
			'country' => $this->country,
			'category' => $this->category->name,
			'mature_content' => $this->matureContent,
		];
	}
}
