<?php

namespace App\Twig;

use App\Entity\Category;
use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayError;
use App\Entity\FixedHolidayMetadata;
use App\Entity\FixedHolidaySuggestion;
use App\Entity\FloatingHoliday;
use App\Entity\FloatingHolidayError;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\FloatingHolidaySuggestion;
use App\Entity\Language;
use App\Entity\Poll;
use App\Entity\ReportState;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\RuntimeExtensionInterface;

class AdminNavRuntime implements RuntimeExtensionInterface
{
	private ?array $data = null;

	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	public function getData(): array
	{
		if ($this->data !== null) {
			return $this->data;
		}

		$reportsPending = 0;
		foreach ([FixedHolidaySuggestion::class, FloatingHolidaySuggestion::class, FixedHolidayError::class, FloatingHolidayError::class] as $cls) {
			$reportsPending += $this->entityManager->getRepository($cls)
				->count(['reportState' => ReportState::REPORTED]);
		}

		$translationCounts = $this->countTranslationsByLanguage();
		$translationTotal = $translationCounts['pl'] ?? 0;
		$translateIncomplete = 0;
		$languages = $this->entityManager->getRepository(Language::class)
			->findAll();
		foreach ($languages as $language) {
			if ($language->code === 'pl') {
				continue;
			}
			if (($translationCounts[$language->code] ?? 0) < $translationTotal) {
				$translateIncomplete++;
			}
		}

		return $this->data = [
			'fixedCount' => $this->entityManager->getRepository(FixedHolidayMetadata::class)
				->count([]),
			'floatingCount' => $this->entityManager->getRepository(FloatingHolidayMetadata::class)
				->count([]),
			'tagCount' => $this->entityManager->getRepository(Category::class)
				->count([]),
			'reportsPending' => $reportsPending,
			'translateIncomplete' => $translateIncomplete,
			'pollCount' => $this->entityManager->getRepository(Poll::class)
				->count([]),
		];
	}

	private function countTranslationsByLanguage(): array
	{
		$counts = [];
		foreach ([FixedHoliday::class, FloatingHoliday::class] as $entityClass) {
			$rows = $this->entityManager->createQueryBuilder()
				->select('IDENTITY(h.language) AS code, COUNT(h.name) AS cnt')
				->from($entityClass, 'h')
				->groupBy('h.language')
				->getQuery()
				->getResult();
			foreach ($rows as $row) {
				$counts[$row['code']] = ($counts[$row['code']] ?? 0) + (int)$row['cnt'];
			}
		}
		return $counts;
	}
}
