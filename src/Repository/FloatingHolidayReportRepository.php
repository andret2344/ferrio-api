<?php

namespace App\Repository;

use App\Entity\FixedHolidayReport;
use App\Entity\FloatingHolidayReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FloatingHolidayReportRepository extends ServiceEntityRepository {
	public function __construct(ManagerRegistry $registry) {
		parent::__construct($registry, FloatingHolidayReport::class);
	}
}
