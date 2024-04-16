<?php

namespace App\Repository;

use App\Entity\FixedHolidayReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FixedHolidayReportRepository extends ServiceEntityRepository {
	public function __construct(ManagerRegistry $registry) {
		parent::__construct($registry, FixedHolidayReport::class);
	}
}
