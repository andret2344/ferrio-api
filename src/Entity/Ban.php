<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Override;

#[ORM\Entity]
class Ban implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer')]
	private(set) int $id;

	#[ORM\Column(unique: true)]
	private(set) string $userId;

	#[ORM\Column(length: 2047)]
	private(set) string $reason;

	#[ORM\Column(type: 'datetimetz_immutable')]
	private DateTimeImmutable $datetime;

	public function __construct(string $userId, string $reason, DateTimeImmutable $datetime)
	{
		$this->userId = $userId;
		$this->reason = $reason;
		$this->datetime = $datetime;
	}

	#[Override]
	#[ArrayShape([
		'id' => 'integer',
		'userId' => 'string',
		'reason' => 'string',
		'datetime' => 'string'
	])]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'userId' => $this->userId,
			'reason' => $this->reason,
			'datetime' => $this->datetime->format('Y-m-d H:i:s')
		];
	}
}
