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
}
