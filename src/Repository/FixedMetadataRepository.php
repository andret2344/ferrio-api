<?php

namespace App\Repository;

use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FixedMetadataRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, FixedHolidayMetadata::class);
	}

	public function findAllByLanguage(string $language)
	{
		return $this->createQueryBuilder('m')
			->innerJoin(FixedHoliday::class, 'h', 'WITH', 'm.id = h.metadata')
			->select(['m.id', 'm.day', 'm.month', 'h.name', 'h.description', 'm.matureContent'])
			->where('h.language = :code')
			->setParameter('code', $language)
			->getQuery()
			->execute();
	}
}
