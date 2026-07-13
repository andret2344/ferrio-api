<?php

namespace App\Handler;

use App\DTO\FloatingReportDTO;
use App\Entity\FloatingHolidayError;
use App\Entity\FloatingHolidayMetadata;
use Override;

readonly class FloatingHolidayErrorHandler extends AbstractErrorReportHandler
{
	#[Override]
	protected function getDtoClass(): string
	{
		return FloatingReportDTO::class;
	}

	#[Override]
	protected function getErrorEntityClass(): string
	{
		return FloatingHolidayError::class;
	}

	#[Override]
	protected function getMetadataEntityClass(): string
	{
		return FloatingHolidayMetadata::class;
	}
}
