<?php

namespace App\Handler;

use App\DTO\FloatingReportDTO;
use App\Entity\FloatingHolidayError;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\Language;
use App\Entity\ReportType;
use Override;

readonly class FloatingHolidayErrorHandler extends AbstractErrorReportHandler
{
	#[Override]
	protected function validatePayload(object $payload): array
	{
		if (!$payload instanceof FloatingReportDTO) {
			throw new \InvalidArgumentException('Expected FloatingReportDTO');
		}
		return [
			'language' => $payload->language,
			'metadata' => $payload->metadata,
			'reportType' => $payload->reportType,
			'description' => $payload->description,
			'comment' => $payload->comment,
		];
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
	protected function createErrorEntity(string $userId, Language $language, object $metadata, ReportType $reportType, ?string $description, ?string $comment): object
	{
		return new FloatingHolidayError($userId, $language, $metadata, $reportType, $description, $comment);
	}
}
