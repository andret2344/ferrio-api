<?php

namespace App\Handler;

use App\DTO\FixedReportDTO;
use App\Entity\FixedHolidayError;
use App\Entity\FixedHolidayMetadata;
use Override;

readonly class FixedHolidayErrorHandler extends AbstractErrorReportHandler
{
	#[Override]
	protected function getDtoClass(): string
	{
		return FixedReportDTO::class;
	}

	#[Override]
	protected function getErrorEntityClass(): string
	{
		return FixedHolidayError::class;
	}

	#[Override]
	protected function getMetadataEntityClass(): string
	{
		return FixedHolidayMetadata::class;
	}
}
