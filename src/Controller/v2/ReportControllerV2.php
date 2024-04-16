<?php

namespace App\Controller\v2;

use App\Entity\FixedHolidayMetadata;
use App\Entity\FixedHolidayReport;
use App\Entity\ReportType;
use App\Repository\FixedHolidayReportRepository;
use App\Repository\FixedMetadataRepository;
use App\Repository\FloatingHolidayReportRepository;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(['/report', '/v2/report'], name: 'v2_report_')]
class ReportControllerV2 extends AbstractController {
	public function __construct(private readonly EntityManagerInterface          $entityManager,
								private readonly FixedHolidayReportRepository    $fixedHolidayReportRepository,
								private readonly FloatingHolidayReportRepository $floatingHolidayReportRepository,
								private readonly LanguageRepository              $languageRepository,
								private readonly FixedMetadataRepository         $fixedMetadataRepository) {
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

	/**
	 * @throws OptimisticLockException
	 * @throws ORMException
	 */
	#[Route('/fixed', name: 'post_fixed', methods: ['POST'])]
	public function postFixed(Request $request): Response {
		$data = json_decode($request->getContent(), true);
		$language = $this->languageRepository->findOneBy(['code' => $data['language']]);
		/** @var FixedHolidayMetadata $metadata */
		$metadata = $this->fixedMetadataRepository->findOneBy(['id' => $data['metadata']]);
		$reportType = ReportType::from($data['report_type']);
		$additionalDescription = $data['description'] ?? null;
		$report = new FixedHolidayReport(null, $language, $metadata, $reportType, $data['data'], $additionalDescription);
		$metadata->addReport($report);
		$this->entityManager->persist($metadata);
		$this->entityManager->flush();
		return new Response(null, 204);
	}

	#[Route('/floating', name: 'post_floating', methods: ['GET'])]
	public function postFloating(Request $request): Response {
		$data = $request->getContent();

		return new JsonResponse($data);
	}
}
