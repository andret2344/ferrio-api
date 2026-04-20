<?php

namespace App\Repository;

use App\Entity\Country;
use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

	public function findAllByLanguage(string $language, int $offset = 0, int $limit = 1_000_000, bool $matureContent = false, ?int $day = null, ?int $month = null, ?string $country = null): array
	{
		$qb = $this->createQueryBuilder('h')
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
			->addOrderBy('m.day', 'ASC');

		if ($month !== null) {
			$qb->andWhere('m.month = :month')->setParameter('month', $month);
		}
		if ($day !== null) {
			$qb->andWhere('m.day = :day')->setParameter('day', $day);
		}
		if ($country !== null) {
			$qb->andWhere('c.isoCode = :country')->setParameter('country', $country);
		}

		return $qb->getQuery()->getResult();
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
		return $this->getEntityManager()->createQueryBuilder()
			->select([
				'm.day AS day',
				'm.month AS month',
				'm.id AS id',
				'h1.name AS nameFrom',
				'h1.description AS descriptionFrom',
				'h2.name AS nameTo',
				'h2.description AS descriptionTo',
			])
			->from(FixedHoliday::class, 'h1')
			->join('h1.metadata', 'm')
			->leftJoin(FixedHoliday::class, 'h2', 'WITH', 'h2.metadata = m.id AND h2.language = :langTo')
			->where('h1.language = :langFrom')
			->setParameter('langFrom', $languageFrom)
			->setParameter('langTo', $languageTo)
			->orderBy('m.month', 'ASC')
			->addOrderBy('m.day', 'ASC')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->getQuery()
			->getResult();
	}
}
