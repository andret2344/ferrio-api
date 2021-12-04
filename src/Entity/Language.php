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

	#[ORM\Column(type: 'string', length: 63, unique: true)]
	private string $name;

	#[ORM\Column(type: 'integer')]
	private int $releaseId;

	#[ORM\Column(type: 'string', unique: true)]
	private string $url;

	public function __construct(string $code, string $name, string $releaseId, string $url) {
		$this->code = $code;
		$this->name = $name;
		$this->releaseId = $releaseId;
		$this->url = $url;
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

	public function getUrl(): string {
		return $this->url;
	}

	#[ArrayShape([
		'uniLanguage' => 'string',
		'language' => 'string',
		'code' => 'string',
		'name' => 'string',
		'releaseId' => 'int',
		'url' => 'string'
	])]
	public function jsonSerialize(): array {
		return [
			'uniLanguage' => $this->code,
			'language' => $this->name,
			'code' => $this->code,
			'name' => $this->name,
			'releaseId' => $this->releaseId,
			'url' => $this->url
		];
	}
}
