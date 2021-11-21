<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;

#[ORM\Entity]
class Language implements JsonSerializable {
	#[ORM\Id]
	#[ORM\Column(type: 'string', length: 31)]
	private string $code;

	#[ORM\Id]
	#[ORM\Column(type: 'string', length: 63)]
	private string $name;

	#[ORM\Column(type: 'integer')]
	private int $releaseId;

	public function __construct(string $code, string $name, string $releaseId) {
		$this->code = $code;
		$this->name = $name;
		$this->releaseId = $releaseId;
	}

	public function getCode(): string {
		return $this->code;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getReleaseId(): int|string {
		return $this->releaseId;
	}

	#[ArrayShape([
		'uniLanguage' => "string",
		'language' => "string",
		'code' => "string",
		'name' => "string",
		'releaseId' => "int|string"
	])]
	public function jsonSerialize(): array {
		return [
			'uniLanguage' => $this->code,
			'language' => $this->name,
			'code' => $this->code,
			'name' => $this->name,
			'releaseId' => $this->releaseId
		];
	}
}
