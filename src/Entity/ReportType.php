<?php

namespace App\Entity;

enum ReportType: string {
	case WRONG_DATE = 'WRONG_DATE';
	case WRONG_NAME = 'WRONG_NAME';
	case WRONG_DESCRIPTION = 'WRONG_DESCRIPTION';
	case NOT_EXISTS = 'NOT_EXISTS';
	case OTHER = 'OTHER';
}
