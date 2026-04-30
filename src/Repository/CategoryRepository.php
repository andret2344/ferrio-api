<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CategoryRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, Category::class);
	}

	/**
	 * Returns all categories with translations and per-type usage counters.
	 *
	 * @return array<int, array{category: Category, fixedCount: int, floatingCount: int}>
	 */
	public function findAllWithCounts(): array
	{
		/** @var Category[] $categories */
		$categories = $this->createQueryBuilder('c')
			->leftJoin('c.translations', 't')
			->addSelect('t')
			->orderBy('c.slug', 'ASC')
			->getQuery()
			->getResult();

		if (empty($categories)) {
			return [];
		}

		$ids = array_map(fn(Category $c) => $c->id, $categories);

		$counts = $this->createQueryBuilder('c')
			->select('c.id AS id, COUNT(DISTINCT fhm.id) AS fixedCount, COUNT(DISTINCT flhm.id) AS floatingCount')
			->leftJoin('c.fixedHolidays', 'fhm')
			->leftJoin('c.floatingHolidays', 'flhm')
			->where('c.id IN (:ids)')
			->setParameter('ids', $ids)
			->groupBy('c.id')
			->getQuery()
			->getResult();

		$countsById = [];
		foreach ($counts as $row) {
			$countsById[$row['id']] = $row;
		}

		$result = [];
		foreach ($categories as $category) {
			$result[] = [
				'category' => $category,
				'fixedCount' => (int)($countsById[$category->id]['fixedCount'] ?? 0),
				'floatingCount' => (int)($countsById[$category->id]['floatingCount'] ?? 0),
			];
		}
		return $result;
	}
}
