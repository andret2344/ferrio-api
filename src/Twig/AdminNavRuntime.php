<?php

namespace App\Twig;

use App\Service\AdminMetricsService;
use Twig\Extension\RuntimeExtensionInterface;

class AdminNavRuntime implements RuntimeExtensionInterface
{
	private ?array $data = null;

	public function __construct(private readonly AdminMetricsService $metrics)
	{
	}

	public function getData(): array
	{
		return $this->data ??= [
			'fixedCount' => $this->metrics->fixedHolidayCount(),
			'floatingCount' => $this->metrics->floatingHolidayCount(),
			'tagCount' => $this->metrics->tagCount(),
			'languageCount' => $this->metrics->languageCount(),
			'reportsPending' => $this->metrics->reportsPendingTotal(),
		];
	}
}
