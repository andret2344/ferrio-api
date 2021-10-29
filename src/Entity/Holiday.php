<?php

namespace App\Entity;

use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;

class Holiday implements JsonSerializable {
	private int $id;
	private string $name;
	private string $description;
	private bool $usual;
	private string|null $link;

	public function __construct(int $id, string $name, string $description, bool $usual = false, ?string $link = null) {
		$this->id = $id;
		$this->name = $name;
		$this->description = $description;
		$this->usual = $usual;
		$this->link = $link;
	}

	#[ArrayShape([
		'id' => "int",
		'name' => "string",
		'description' => "string",
		'usual' => "bool",
		'link' => "null|string"
	])]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'description' => $this->description,
			'usual' => $this->usual,
			'link' => $this->link
		];
	}
}
