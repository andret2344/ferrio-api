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

	#[Route('/', name: 'get_all', methods: ['GET'])]
	public function getAll(): Response {
		return new JsonResponse([
			'fixed' => $this->fixedHolidayReportRepository->findAll(),
			'floating' => $this->floatingHolidayReportRepository->findAll()
		]);
	}

	#[Route('/fixed', name: 'get_all_fixed', methods: ['GET'])]
	public function getAllFixed(): Response {
		return new JsonResponse($this->fixedHolidayReportRepository->findAll());
	}

	#[Route('/floating', name: 'get_all_floating', methods: ['GET'])]
	public function getAllFloating(): Response {
		return new JsonResponse($this->floatingHolidayReportRepository->findAll());
	}

	#[Route('/fixed/{id}', name: 'get_one_fixed', methods: ['GET'])]
	public function getFixed(int $id): Response {
		return new JsonResponse($this->fixedHolidayReportRepository->findOneBy(['id' => $id]));
	}

	#[Route('/floating/{id}', name: 'get_one_floating', methods: ['GET'])]
	public function getFloating(int $id): Response {
		return new JsonResponse($this->floatingHolidayReportRepository->findOneBy(['id' => $id]));
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
