<?php

namespace App\Entity;

use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;

class Language implements JsonSerializable {
	private string $language;
	private string $uniLanguage;

	public function __construct(string $language, string $uniLanguage) {
		$this->language = $language;
		$this->uniLanguage = $uniLanguage;
	}

	public function getLanguage(): string {
		return $this->language;
	}

	public function getUniLanguage(): string {
		return $this->uniLanguage;
	}

	#[ArrayShape(['language' => "string", 'uniLanguage' => "string"])]
	public function jsonSerialize(): array {
		return [
			'language' => $this->language,
			'uniLanguage' => $this->uniLanguage
		];
	}
}
