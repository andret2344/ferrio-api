<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Override;

#[ORM\Entity]
class Ban implements JsonSerializable {
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer')]
	private int $id;

	#[ORM\Column(length: 63, unique: true)]
	private string $uuid;

	#[ORM\Column(length: 2047)]
	private string $reason;

	#[ORM\Column]
	private DateTimeImmutable $datetime;

	public function __construct(string $uuid, string $reason, DateTimeImmutable $datetime) {
		$this->uuid = $uuid;
		$this->reason = $reason;
		$this->datetime = $datetime;
	}

	public function getId(): string {
		return $this->id;
	}

	public function getReason(): string {
		return $this->reason;
	}

	#[Override]
	#[ArrayShape([
		'id' => 'integer',
		'uuid' => 'string',
		'reason' => 'string',
		'datetime' => 'string'
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'uuid' => $this->uuid,
			'reason' => $this->reason,
			'datetime' => $this->datetime->format('Y-m-d H:i:s')
		];
	}
}
