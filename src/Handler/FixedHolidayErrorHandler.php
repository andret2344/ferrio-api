<?php

namespace App\Handler;

use App\DTO\FixedReportDTO;
use App\Entity\Country;
use App\Entity\FixedHolidayError;
use App\Entity\FixedHolidayMetadata;
use App\Entity\Language;
use App\Entity\Platform;
use App\Entity\ReportType;
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

	#[Override]
	protected function createErrorEntity(string $userId, Language $language, object $metadata, ReportType $reportType, ?string $description, ?string $comment, Platform $platform, ?string $realDevice, ?Country $deviceCountry, ?string $osVersion, ?string $appVersion, ?int $appBuild): object
	{
		return new FixedHolidayError($userId, $language, $metadata, $reportType, $description, $comment, $platform, $realDevice, $deviceCountry, $osVersion, $appVersion, $appBuild);
	}
}
