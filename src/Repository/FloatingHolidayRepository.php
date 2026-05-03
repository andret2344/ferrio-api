<?php

namespace App\Repository;

use App\Entity\FloatingHoliday;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FloatingHolidayRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, FloatingHoliday::class);
	}

	/**
	 * @return FloatingHoliday[]
	 */
	public function findAllByLanguage(string $language, ?string $country = null): array
	{
		$qb = $this->createQueryBuilder('h')
			->select('h', 'm')
			->join('h.metadata', 'm')
			->where('h.language = :language')
			->setParameter('language', $language);

		if ($country !== null) {
			$qb->join('m.country', 'c')
				->andWhere('c.isoCode = :country')
				->setParameter('country', $country);
		}

		return $qb->getQuery()->getResult();
	}
}
