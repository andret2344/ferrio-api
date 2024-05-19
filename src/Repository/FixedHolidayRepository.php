<?php

namespace App\Repository;

use App\Entity\Country;
use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

class FixedHolidayRepository extends ServiceEntityRepository {
	public function __construct(ManagerRegistry $registry) {
		parent::__construct($registry, FixedHoliday::class);
	}

	/**
	 * @param string $language
	 * @param int $day
	 * @param int $month
	 *
	 * @return array|FixedHoliday[]
	 */
	public function findAt(string $language, int $day, int $month): array {
		return $this->createQueryBuilder('h')
			->join(FixedHolidayMetadata::class, 'm', 'WITH', 'h.metadata = m.id')
			->where('h.language = :language')
			->andWhere('m.day = :day')
			->andWhere('m.month = :month')
			->setParameter('language', $language)
			->setParameter('day', $day)
			->setParameter('month', $month)
			->getQuery()
			->getResult();
	}

	/**
	 * @param string $language
	 *
	 * @return array
	 */
	public function findAllByLanguage(string $language, int $offset = 0, int $limit = 1_000_000): array {
		return $this->createQueryBuilder('h')
			->select([
				'm.id',
				'm.month',
				'm.day',
				'h.name',
				'm.usual',
				'h.description',
				'h.url',
				'c.englishName AS countryName',
				'c.isoCode AS countryCode',
				'm.matureContent AS matureContent'
			])
			->join(FixedHolidayMetadata::class, 'm', 'WITH', 'h.metadata = m.id')
			->leftJoin(Country::class, 'c', 'WITH', 'c.isoCode = m.country')
			->where('h.language = :language')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->setParameter('language', $language)
			->orderBy('m.month', 'ASC')
			->addOrderBy('m.day', 'ASC')
			->getQuery()
			->getResult();
	}

	/**
	 * @param string $language
	 * @param array $array
	 *
	 * @return array|string[]
	 */
	public function check(string $language, array $array): array {
		$existingNames = $this->createQueryBuilder('h2')
			->select('h2.name')
			->join('h2.metadata', 'm')
			->andWhere('h2.language = :language')
			->setParameter('language', $language)
			->getQuery()
			->getResult();

		$existingNames = array_column($existingNames, 'name');
		return array_diff($array, $existingNames);
	}

	public function findAllAggregatedById(string $languageFrom, string $languageTo, int $offset = 0, int $limit = 1_000_000) {
		$rsm = new ResultSetMapping();
		$rsm->addEntityResult(FixedHoliday::class, 'h');
		$rsm->addScalarResult('id', 'id');
		$rsm->addScalarResult('day', 'day');
		$rsm->addScalarResult('month', 'month');
		$rsm->addScalarResult('name_from', 'nameFrom');
		$rsm->addScalarResult('description_from', 'descriptionFrom');
		$rsm->addScalarResult('name_to', 'nameTo');
		$rsm->addScalarResult('description_to', 'descriptionTo');
		$sql = "SELECT m1.day         AS day,
					   m1.month       AS month,
					   h1.metadata_id AS id,
					   h1.name        AS name_from,
					   h1.description AS description_from,
					   h2.name        AS name_to,
					   h2.description AS description_to
				FROM (SELECT * FROM fixed_holiday WHERE fixed_holiday.language_code = :langFrom) as h1
						 LEFT JOIN (SELECT * FROM fixed_holiday WHERE fixed_holiday.language_code = :langTo) as h2
								   ON h1.metadata_id = h2.metadata_id
						 INNER JOIN fixed_holiday_metadata m1 ON h1.metadata_id = m1.id
				ORDER BY month, day
				LIMIT :limit OFFSET :offset;";
		$query = $this->_em->createNativeQuery($sql, $rsm);
		$query->setParameter('langFrom', $languageFrom);
		$query->setParameter('langTo', $languageTo);
		$query->setParameter('limit', $limit, ParameterType::INTEGER);
		$query->setParameter('offset', $offset, ParameterType::INTEGER);
		return $query->getResult();
	}
}
