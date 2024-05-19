<?php

namespace App\Repository;

use App\Entity\MissingFloatingHoliday;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MissingFloatingHolidayRepository extends ServiceEntityRepository {
	public function __construct(ManagerRegistry $registry) {
		parent::__construct($registry, MissingFloatingHoliday::class);
	}
}
