<?php

namespace App\Handler;

use App\DTO\FixedReportDTO;
use App\Entity\FixedHolidayError;
use App\Entity\FixedHolidayMetadata;
use App\Entity\Language;
use App\Entity\ReportType;
use Override;

readonly class FixedHolidayErrorHandler extends AbstractErrorReportHandler
{
	#[Override]
	protected function validatePayload(object $payload): array
	{
		if (!$payload instanceof FixedReportDTO) {
			throw new \InvalidArgumentException('Expected FixedReportDTO');
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
		return FixedHolidayError::class;
	}

	#[Override]
	protected function getMetadataEntityClass(): string
	{
		return FixedHolidayMetadata::class;
	}

	#[Override]
	protected function createErrorEntity(string $userId, Language $language, object $metadata, ReportType $reportType, ?string $description, ?string $comment): object
	{
		return new FixedHolidayError($userId, $language, $metadata, $reportType, $description, $comment);
	}
}
