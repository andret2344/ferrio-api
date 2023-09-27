<?php

namespace App\Service;

use App\Repository\FixedMetadataRepository;
use App\Repository\FloatingMetadataRepository;

class TranslationService {
	public function __construct(private readonly FixedMetadataRepository    $fixedMetadataRepository,
								private readonly FloatingMetadataRepository $floatingMetadataRepository) {
	}

	public function get(string $language): array {
		return $this->fixedMetadataRepository->findAllByLanguage($language);
	}
}
