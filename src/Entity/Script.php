<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Script
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer')]
	private(set) int $id;

	#[ORM\Column(type: 'text')]
	public string $content;

	public function __construct(int $id, string $content)
	{
		$this->id = $id;
		$this->content = $content;
	}
}
