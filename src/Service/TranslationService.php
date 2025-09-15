<?php

namespace App\Service;

use App\Repository\FixedMetadataRepository;

readonly class TranslationService
{
	public function __construct(private FixedMetadataRepository $fixedMetadataRepository)
	{
	}

	public function get(string $language): array
	{
		return $this->fixedMetadataRepository->findAllByLanguage($language);
	}
}
