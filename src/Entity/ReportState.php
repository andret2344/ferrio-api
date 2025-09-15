<?php

namespace App\Entity;

enum ReportState: string
{
	case REPORTED = 'REPORTED';
	case APPLIED = 'APPLIED';
	case DECLINED = 'DECLINED';
	case ON_HOLD = 'ON_HOLD';
}
