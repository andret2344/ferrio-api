<?php

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class JsonExtension extends AbstractExtension
{
	#[Override]
	public function getFilters(): array
	{
		return [
			new TwigFilter('json_pretty', $this->jsonPretty(...)),
		];
	}

	public function jsonPretty(?string $json): string
	{
		if ($json === null || trim($json) === '') {
			return '';
		}
		$decoded = json_decode($json, true);
		if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
			return $json;
		}
		return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
}
