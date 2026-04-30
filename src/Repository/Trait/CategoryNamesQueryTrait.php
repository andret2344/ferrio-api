<?php

namespace App\Repository\Trait;

trait CategoryNamesQueryTrait
{
	/**
	 * Bulk-loads translated category names for the given metadata ids, falling back to the
	 * tag slug when no translation exists in the requested language. Empty input returns [].
	 *
	 * @param int[] $metadataIds
	 * @return array<int, string[]> metadata id => list of names (slug fallback), sorted by slug
	 */
	public function findCategoryNames(array $metadataIds, string $language): array
	{
		if (empty($metadataIds)) {
			return [];
		}

		$rows = $this->createQueryBuilder('m')
			->select('m.id AS metadataId', 'c.slug AS slug', 't.name AS translatedName')
			->join('m.categories', 'c')
			->leftJoin('c.translations', 't', 'WITH', 't.language = :language')
			->where('m.id IN (:ids)')
			->setParameter('ids', $metadataIds)
			->setParameter('language', $language)
			->orderBy('c.slug', 'ASC')
			->getQuery()
			->getResult();

		$result = [];
		foreach ($rows as $row) {
			$result[$row['metadataId']][] = $row['translatedName'] ?? $row['slug'];
		}
		return $result;
	}
}
