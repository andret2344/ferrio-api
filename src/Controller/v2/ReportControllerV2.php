<?php

namespace App\Controller\v2;

use App\Entity\FixedHolidayMetadata;
use App\Entity\FixedHolidayReport;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\FloatingHolidayReport;
use App\Entity\ReportType;
use App\Repository\FixedHolidayReportRepository;
use App\Repository\FixedMetadataRepository;
use App\Repository\FloatingHolidayReportRepository;
use App\Repository\FloatingMetadataRepository;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(['/report', '/v2/report'], name: 'v2_report_')]
class ReportControllerV2 extends AbstractController {
	public function __construct(
		private readonly EntityManagerInterface          $entityManager,
		private readonly LanguageRepository              $languageRepository,
		private readonly FixedHolidayReportRepository    $fixedHolidayReportRepository,
		private readonly FixedMetadataRepository         $fixedMetadataRepository,
		private readonly FloatingHolidayReportRepository $floatingHolidayReportRepository,
		private readonly FloatingMetadataRepository      $floatingMetadataRepository) {
	}

	#[Route('/{uid<^\S+$>}/floating', name: 'get_fixed_by_uid', methods: ['GET'])]
	public function getFixedByUid(string $uid): Response {
		return new JsonResponse($this->fixedHolidayReportRepository->findBy(['userId' => $uid]));
	}

	#[Route('/{uid<^\S+$>}/floating', name: 'get_floating_by_uid', methods: ['GET'])]
	public function getFloatingByUid(string $uid): Response {
		return new JsonResponse($this->floatingHolidayReportRepository->findBy(['userId' => $uid]));
	}

	#[Route('/fixed', name: 'post_fixed', methods: ['POST'])]
	public function postFixed(Request $request): Response {
		$data = json_decode($request->getContent(), true);
		$language = $this->languageRepository->findOneBy(['code' => $data['language']]);
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->fixedMetadataRepository->findOneBy(['id' => $data['metadata']]);
		$reportType = ReportType::from($data['report_type']);
		$description = $data['description'] ?? null;
		$userId = $data['user_id'] ?? null;
		$report = new FixedHolidayReport(null, $userId, $language, $metadata, $reportType, $description);
		$metadata->addReport($report);
		$this->entityManager->persist($metadata);
		$this->entityManager->flush();
		return new Response(null, 204);
	}

	#[Route('/floating', name: 'post_floating', methods: ['POST'])]
	public function postFloating(Request $request): Response {
		$data = json_decode($request->getContent(), true);
		$language = $this->languageRepository->findOneBy(['code' => $data['language']]);
		/** @var FloatingHolidayMetadata $metadata */
		$metadata = $this->floatingMetadataRepository->findOneBy(['id' => $data['metadata']]);
		$reportType = ReportType::from($data['report_type']);
		$description = $data['description'] ?? null;
		$userId = $data['user_id'] ?? null;
		$report = new FloatingHolidayReport(null, $userId, $language, $metadata, $reportType, $description);
		$metadata->addReport($report);
		$this->entityManager->persist($metadata);
		$this->entityManager->flush();
		return new Response(null, 204);
	}
}
