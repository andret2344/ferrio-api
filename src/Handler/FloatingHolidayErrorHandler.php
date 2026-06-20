<?php

namespace App\Handler;

use App\DTO\FloatingReportDTO;
use App\Entity\Country;
use App\Entity\FloatingHolidayError;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\Language;
use App\Entity\Platform;
use App\Entity\ReportType;
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

	#[Override]
	protected function createErrorEntity(string $userId, Language $language, object $metadata, ReportType $reportType, ?string $description, ?string $comment, Platform $platform, ?string $realDevice, ?Country $deviceCountry): object
	{
		return new FloatingHolidayError($userId, $language, $metadata, $reportType, $description, $comment, $platform, $realDevice, $deviceCountry);
	}
}
