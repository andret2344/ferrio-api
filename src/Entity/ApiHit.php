<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'api_hit')]
class ApiHit
{
	#[ORM\Id]
	#[ORM\Column(type: 'datetime_immutable')]
	private(set) DateTimeImmutable $bucketHour;

	#[ORM\Id]
	#[ORM\Column(length: 8)]
	private(set) string $version;

	#[ORM\Column(options: ['unsigned' => true, 'default' => 0])]
	private(set) int $hits = 0;
}
