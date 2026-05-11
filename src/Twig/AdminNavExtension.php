<?php

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminNavExtension extends AbstractExtension
{
	#[Override]
	public function getFunctions(): array
	{
		return [
			new TwigFunction('admin_nav', [AdminNavRuntime::class, 'getData']),
		];
	}
}
