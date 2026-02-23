<?php

namespace App\Repository;

use App\Entity\Country;
use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

class FixedHolidayRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, FixedHoliday::class);
	}

	public function findAt(string $language, int $day, int $month, bool $matureContent = false): array
	{
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
			->join(FixedHolidayMetadata::class, 'm', 'ON', 'h.metadata = m.id')
			->leftJoin(Country::class, 'c', 'ON', 'c.isoCode = m.country')
			->where('h.language = :language')
			->andWhere('m.day = :day')
			->andWhere('m.month = :month')
			->andWhere('m.matureContent IN (false, :matureContent)')
			->setParameter('language', $language)
			->setParameter('day', $day)
			->setParameter('month', $month)
			->setParameter('matureContent', $matureContent)
			->getQuery()
			->getResult();
	}

	public function findAllByLanguage(string $language, int $offset = 0, int $limit = 1_000_000, bool $matureContent = false): array
	{
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
			->join(FixedHolidayMetadata::class, 'm', 'ON', 'h.metadata = m.id')
			->leftJoin(Country::class, 'c', 'ON', 'c.isoCode = m.country')
			->where('h.language = :language')
			->andWhere('m.matureContent IN (false, :matureContent)')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->setParameter('language', $language)
			->setParameter('matureContent', $matureContent)
			->orderBy('m.month', 'ASC')
			->addOrderBy('m.day', 'ASC')
			->getQuery()
			->getResult();
	}

	public function check(string $language, array $array): array
	{
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

	public function findAllAggregatedById(string $languageFrom, string $languageTo, int $offset = 0, int $limit = 1_000_000): array
	{
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
		$query = $this->getEntityManager()
			->createNativeQuery($sql, $rsm);
		$query->setParameter('langFrom', $languageFrom);
		$query->setParameter('langTo', $languageTo);
		$query->setParameter('limit', $limit, ParameterType::INTEGER);
		$query->setParameter('offset', $offset, ParameterType::INTEGER);
		return $query->getResult();
	}
}
