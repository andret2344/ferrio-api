<?php

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class StringPadExtension extends AbstractExtension
{
	#[Override]
	public function getFilters(): array
	{
		return [
			new TwigFilter('pad', $this->pad(...)),
		];
	}

	public function pad(string $input, int $length, string $element): string
	{
		return str_pad($input, $length, $element, STR_PAD_LEFT);
	}
}
