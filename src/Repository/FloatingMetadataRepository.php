<?php

namespace App\Repository;

use App\Entity\FloatingHolidayMetadata;
use App\Repository\Trait\CategoryNamesQueryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FloatingMetadataRepository extends ServiceEntityRepository
{
	use CategoryNamesQueryTrait;

	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, FloatingHolidayMetadata::class);
	}
}
