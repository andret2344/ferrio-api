<?php

namespace App\Repository;

use App\Entity\Holiday;
use App\Entity\HolidayMetadata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\ManagerRegistry;

class HolidayRepository extends ServiceEntityRepository {
	public function __construct(ManagerRegistry $registry) {
		parent::__construct($registry, Holiday::class);
	}

	/**
	 * @param string $language
	 * @param int $day
	 * @param int $month
	 * @return array|Holiday[]
	 */
	public function findAt(string $language, int $day, int $month): array {
		return $this->createQueryBuilder('h')
			->join(HolidayMetadata::class, 'm', 'WITH', 'h.metadata = m.id')
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
			->select(['m.id, m.month', 'm.day', 'h.name', 'm.usual', 'h.description', 'h.url'])
			->join(HolidayMetadata::class, 'm', 'WITH', 'h.metadata = m.id')
			->where('h.language = :language')
			->setParameter('language', $language)
			->orderBy('m.month', 'ASC')
			->addOrderBy('m.day', 'ASC')
			->getQuery()
			->getResult();
	}
}
