<?php

namespace App\Entity;

use App\Repository\PollRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: PollRepository::class)]
class Poll implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer')]
	private(set) int $id;

	#[ORM\Column]
	private(set) string $name;

	#[ORM\Column(type: 'text')]
	private(set) string $question;

	#[ORM\Column(type: 'datetimetz_immutable')]
	private(set) DateTimeImmutable $start;

	#[ORM\Column(type: 'datetimetz_immutable')]
	private(set) DateTimeImmutable $end;

	#[ORM\OneToMany(targetEntity: PollOption::class, mappedBy: 'poll', cascade: ['persist', 'remove'], orphanRemoval: true)]
	private(set) Collection $options;

	#[ORM\OneToMany(targetEntity: PollVote::class, mappedBy: 'poll', cascade: ['remove'])]
	private Collection $votes;

	public function __construct(string $name, string $question, DateTimeImmutable $start, DateTimeImmutable $end)
	{
		$this->name = $name;
		$this->question = $question;
		$this->start = $start;
		$this->end = $end;
		$this->options = new ArrayCollection();
		$this->votes = new ArrayCollection();
	}

	public function isActive(): bool
	{
		$now = new DateTimeImmutable();
		return $now >= $this->start && $now <= $this->end;
	}

	public function addOption(PollOption $option): void
	{
		if (!$this->options->contains($option)) {
			$this->options->add($option);
		}
	}

	#[Override]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'question' => $this->question,
			'options' => array_values(
				array_map(fn(PollOption $o) => $o->jsonSerialize(), $this->options->toArray())
			),
		];
	}
}
