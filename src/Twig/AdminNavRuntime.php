<?php

namespace App\Twig;

use App\Entity\Language;
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
		if ($this->data !== null) {
			return $this->data;
		}
		$fixedCount = $this->metrics->fixedHolidayCount();
		$floatingCount = $this->metrics->floatingHolidayCount();
		$targetLanguages = $this->metrics->targetLanguageCount();
		$countsByKind = $this->metrics->translationCountsByLanguageAndKind();
		$fixedTranslated = $countsByKind['fixed'] ?? [];
		$floatingTranslated = $countsByKind['floating'] ?? [];
		unset($fixedTranslated[Language::DEFAULT_CODE], $floatingTranslated[Language::DEFAULT_CODE]);
		$fixedMissing = max(0, $targetLanguages * $fixedCount - array_sum($fixedTranslated));
		$floatingMissing = max(0, $targetLanguages * $floatingCount - array_sum($floatingTranslated));
		$reportCounts = $this->metrics->reportCounts();
		return $this->data = [
			'fixedCount' => $fixedCount,
			'floatingCount' => $floatingCount,
			'tagCount' => $this->metrics->tagCount(),
			'languageCount' => $this->metrics->languageCount(),
			'reportsPending' => $this->metrics->reportsPendingTotal(),
			'reportsByKind' => array_map(fn(array $c): int => $c['reported'], $reportCounts),
			'fixedMissing' => $fixedMissing,
			'floatingMissing' => $floatingMissing,
		];
	}
}
