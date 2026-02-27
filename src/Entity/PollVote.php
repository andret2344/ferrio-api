<?php

namespace App\Entity;

use App\Repository\PollVoteRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PollVoteRepository::class)]
#[ORM\UniqueConstraint(columns: ['user_id', 'poll_id'])]
class PollVote
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer')]
	private(set) int $id;

	#[ORM\Column]
	private(set) string $userId;

	#[ORM\ManyToOne(targetEntity: PollOption::class, inversedBy: 'votes')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	private(set) PollOption $option;

	#[ORM\ManyToOne(targetEntity: Poll::class, inversedBy: 'votes')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	private(set) Poll $poll;

	#[ORM\Column(type: 'datetimetz_immutable')]
	private(set) DateTimeImmutable $createdAt;

	public function __construct(string $userId, PollOption $option, Poll $poll)
	{
		$this->userId = $userId;
		$this->option = $option;
		$this->poll = $poll;
		$this->createdAt = new DateTimeImmutable();
	}
}
