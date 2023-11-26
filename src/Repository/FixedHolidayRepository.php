<?php

namespace App\Repository;

use App\Entity\Country;
use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FixedHolidayRepository extends ServiceEntityRepository {
	public function __construct(ManagerRegistry $registry) {
		parent::__construct($registry, FixedHoliday::class);
	}

	/**
	 * @param string $language
	 * @param int $day
	 * @param int $month
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
	 * @return array
	 */
	public function findAllByLanguage(string $language): array {
		return $this->createQueryBuilder('h')
			->select(['m.id, m.month', 'm.day', 'h.name', 'm.usual', 'h.description', 'h.url', 'c.englishName AS country'])
			->join(FixedHolidayMetadata::class, 'm', 'WITH', 'h.metadata = m.id')
			->leftJoin(Country::class, 'c', 'WITH', 'c.isoCode = m.country')
			->where('h.language = :language')
			->setParameter('language', $language)
			->orderBy('m.month', 'ASC')
			->addOrderBy('m.day', 'ASC')
			->getQuery()
			->getResult();
	}
}
