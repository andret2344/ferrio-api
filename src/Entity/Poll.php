<?php

namespace App\Entity;

use App\Repository\PollRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PollRepository::class)]
class Poll {
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer', nullable: false)]
	private string $id;

	#[ORM\Column(type: 'text', nullable: false)]
	private string $name;

	#[ORM\Column(type: 'text', nullable: false)]
	private string $content;

	#[ORM\Column(type: 'datetimetz_immutable', nullable: false)]
	private readonly DateTimeImmutable $start;

	#[ORM\Column(type: 'datetimetz_immutable', nullable: false)]
	private readonly DateTimeImmutable $end;
}
