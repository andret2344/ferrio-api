<?php

namespace App\Service;

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
use App\Entity\ReportState;
use App\Enum\Algorithm;
use App\Enum\ReportKind;
use Doctrine\ORM\EntityManagerInterface;

class AdminMetricsService
{
	private const array REPORT_CLASSES = [
		FixedHolidaySuggestion::class,
		FloatingHolidaySuggestion::class,
		FixedHolidayError::class,
		FloatingHolidayError::class,
	];

	private ?array $reportCounts = null;
	private ?array $translationCounts = null;
	private ?array $translationCountsByKind = null;

	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	public function fixedHolidayCount(): int
	{
		return $this->entityManager->getRepository(FixedHoliday::class)
			->count(['language' => Language::DEFAULT_CODE]);
	}

	public function floatingHolidayCount(): int
	{
		return $this->entityManager->getRepository(FloatingHoliday::class)
			->count(['language' => Language::DEFAULT_CODE]);
	}

	public function tagCount(): int
	{
		return $this->entityManager->getRepository(Category::class)
			->count([]);
	}

	/**
	 * @return array<string, array{reported: int, total: int}> keyed by ReportKind value
	 */
	public function reportCounts(): array
	{
		if ($this->reportCounts !== null) {
			return $this->reportCounts;
		}
		$counts = [];
		foreach (ReportKind::cases() as $kind) {
			$repo = $this->entityManager->getRepository($kind->entityClass());
			$counts[$kind->value] = [
				'reported' => $repo->count(['reportState' => ReportState::REPORTED]),
				'total' => $repo->count([]),
			];
		}
		return $this->reportCounts = $counts;
	}

	public function reportsPendingTotal(): int
	{
		return array_sum(array_column($this->reportCounts(), 'reported'));
	}

	/**
	 * @return array<string, int> map of language code => total translated holidays (fixed + floating)
	 */
	public function translationCountsByLanguage(): array
	{
		if ($this->translationCounts !== null) {
			return $this->translationCounts;
		}
		$counts = [];
		foreach ([FixedHoliday::class, FloatingHoliday::class] as $entityClass) {
			$rows = $this->entityManager->createQueryBuilder()
				->select('IDENTITY(h.language) AS code', 'COUNT(h.name) AS cnt')
				->from($entityClass, 'h')
				->groupBy('h.language')
				->getQuery()
				->getResult();
			foreach ($rows as $row) {
				$counts[$row['code']] = ($counts[$row['code']] ?? 0) + (int)$row['cnt'];
			}
		}
		return $this->translationCounts = $counts;
	}

	public function translationTotal(): int
	{
		return $this->translationCountsByLanguage()[Language::DEFAULT_CODE] ?? 0;
	}

	/**
	 * @return array{fixed: array<string, int>, floating: array<string, int>} per-kind map of language code => translated holiday count
	 */
	public function translationCountsByLanguageAndKind(): array
	{
		if ($this->translationCountsByKind !== null) {
			return $this->translationCountsByKind;
		}
		$result = ['fixed' => [], 'floating' => []];
		foreach (['fixed' => FixedHoliday::class, 'floating' => FloatingHoliday::class] as $kind => $entityClass) {
			$rows = $this->entityManager->createQueryBuilder()
				->select('IDENTITY(h.language) AS code', 'COUNT(h.name) AS cnt')
				->from($entityClass, 'h')
				->groupBy('h.language')
				->getQuery()
				->getResult();
			foreach ($rows as $row) {
				$result[$kind][$row['code']] = (int)$row['cnt'];
			}
		}
		return $this->translationCountsByKind = $result;
	}

	public function targetLanguageCount(): int
	{
		return max(0, $this->languageCount() - 1);
	}

	public function languageCount(): int
	{
		return $this->entityManager->getRepository(Language::class)->count([]);
	}

	/**
	 * @return array<int, int> map of metadata id => number of non-Polish translations
	 */
	public function fixedTranslationCountsByMetadata(?int $month = null): array
	{
		return $this->translationCountsByMetadata(FixedHoliday::class, $month);
	}

	/**
	 * @return array<int, int> map of metadata id => number of non-Polish translations
	 */
	public function floatingTranslationCountsByMetadata(): array
	{
		return $this->translationCountsByMetadata(FloatingHoliday::class, null);
	}

	/**
	 * @param class-string $entityClass
	 * @return array<int, int>
	 */
	private function translationCountsByMetadata(string $entityClass, ?int $month): array
	{
		$qb = $this->entityManager->createQueryBuilder()
			->select('IDENTITY(h.metadata) AS metadataId', 'COUNT(h.name) AS cnt')
			->from($entityClass, 'h')
			->where('IDENTITY(h.language) <> :pl')
			->setParameter('pl', Language::DEFAULT_CODE)
			->groupBy('h.metadata');
		if ($month !== null) {
			$qb->join('h.metadata', 'm')
				->andWhere('m.month = :month')
				->setParameter('month', $month);
		}
		$rows = $qb->getQuery()->getResult();
		$counts = [];
		foreach ($rows as $row) {
			$counts[(int)$row['metadataId']] = (int)$row['cnt'];
		}
		return $counts;
	}

	/**
	 * @return array<int, int> map of month number (1-12) => number of fixed holidays, every month present
	 */
	public function fixedCountsByMonth(): array
	{
		$rows = $this->entityManager->createQueryBuilder()
			->select('m.month AS month', 'COUNT(m.id) AS cnt')
			->from(FixedHolidayMetadata::class, 'm')
			->groupBy('m.month')
			->getQuery()
			->getResult();

		$counts = array_fill_keys(range(1, 12), 0);
		foreach ($rows as $row) {
			$counts[(int)$row['month']] = (int)$row['cnt'];
		}
		return $counts;
	}

	/**
	 * @return array<string, int> map of algorithm value => number of floating holidays, biggest first
	 */
	public function floatingCountsByAlgorithm(): array
	{
		$rows = $this->entityManager->createQueryBuilder()
			->select('m.algorithm AS algorithm', 'COUNT(m.id) AS cnt')
			->from(FloatingHolidayMetadata::class, 'm')
			->groupBy('m.algorithm')
			->getQuery()
			->getResult();

		$counts = [];
		foreach ($rows as $row) {
			$algorithm = $row['algorithm'];
			if ($algorithm instanceof Algorithm) {
				$algorithm = $algorithm->value;
			}
			$counts[(string)$algorithm] = (int)$row['cnt'];
		}
		arsort($counts);
		return $counts;
	}

	/**
	 * @return array<int, int> map of metadata id => number of tags
	 */
	public function fixedTagCountsByMetadata(?int $month = null): array
	{
		return $this->tagCountsByMetadata(FixedHolidayMetadata::class, $month);
	}

	/**
	 * @return array<int, int> map of metadata id => number of tags
	 */
	public function floatingTagCountsByMetadata(): array
	{
		return $this->tagCountsByMetadata(FloatingHolidayMetadata::class, null);
	}

	/**
	 * @param class-string $metadataClass
	 * @return array<int, int>
	 */
	private function tagCountsByMetadata(string $metadataClass, ?int $month): array
	{
		$qb = $this->entityManager->createQueryBuilder()
			->select('m.id AS metadataId', 'COUNT(c.id) AS cnt')
			->from($metadataClass, 'm')
			->leftJoin('m.categories', 'c')
			->groupBy('m.id');
		if ($month !== null) {
			$qb->andWhere('m.month = :month')->setParameter('month', $month);
		}
		$rows = $qb->getQuery()->getResult();
		$counts = [];
		foreach ($rows as $row) {
			$counts[(int)$row['metadataId']] = (int)$row['cnt'];
		}
		return $counts;
	}

}
