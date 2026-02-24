<?php

namespace App\Entity;

use App\Repository\PollOptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Override;

#[ORM\Entity(repositoryClass: PollOptionRepository::class)]
class PollOption implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer')]
	private(set) int $id;

	#[ORM\Column]
	private(set) string $text;

	#[ORM\ManyToOne(targetEntity: Poll::class, inversedBy: 'options')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	private(set) Poll $poll;

	#[ORM\OneToMany(targetEntity: PollVote::class, mappedBy: 'option', cascade: ['remove'])]
	private Collection $votes;

	public function __construct(Poll $poll, string $text)
	{
		$this->poll = $poll;
		$this->text = $text;
		$this->votes = new ArrayCollection();
	}

	#[Override]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'text' => $this->text,
		];
	}
}
