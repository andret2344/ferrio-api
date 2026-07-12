<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Override;

#[ORM\Entity]
class Country implements JsonSerializable
{
	#[ORM\Id]
	#[ORM\Column(type: 'string', length: 2, unique: true)]
	private(set) string $isoCode;

	#[ORM\Column(type: 'string', length: 255, unique: true)]
	private(set) string $englishName;

	public function __construct(string $isoCode, string $englishName)
	{
		$this->isoCode = $isoCode;
		$this->englishName = $englishName;
	}

	/**
	 * Normalises a raw device/report country code: uppercases it, and folds empty / literal
	 * 'null' inputs to null. Pure — no DB access — so it is safe to call from anywhere.
	 */
	public static function normalizeCode(?string $code): ?string
	{
		if ($code === null || $code === '' || $code === 'null') {
			return null;
		}
		return strtoupper($code);
	}

	#[Override]
	#[ArrayShape([
		'isoCode' => 'string',
		'englishName' => 'string'
	])]
	public function jsonSerialize(): array
	{
		return [
			'isoCode' => $this->isoCode,
			'englishName' => $this->englishName
		];
	}
}
