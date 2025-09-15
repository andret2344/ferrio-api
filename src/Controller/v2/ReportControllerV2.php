<?php

namespace App\Controller\v2;

use App\Handler\FixedHolidayErrorHandler;
use App\Handler\FloatingHolidayErrorHandler;
use App\Service\BanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/v2/report', name: 'v2_report_')]
class ReportControllerV2 extends AbstractController
{
	public function __construct(
		private readonly BanService                  $banService,
		private readonly FixedHolidayErrorHandler    $fixedHolidayErrorHandler,
		private readonly FloatingHolidayErrorHandler $floatingHolidayErrorHandler)
	{
	}

	#[Route('/{userId<^\S+$>}/fixed', name: 'get_fixed_by_userid', methods: ['GET'])]
	public function getFixedByUserId(string $userId): Response
	{
		return new JsonResponse($this->fixedHolidayErrorHandler->list($userId));
	}

	#[Route('/{userId<^\S+$>}/floating', name: 'get_floating_by_userid', methods: ['GET'])]
	public function getFloatingByUserId(string $userId): Response
	{
		return new JsonResponse($this->floatingHolidayErrorHandler->list($userId));
	}

	#[Route('/fixed', name: 'post_fixed', methods: ['POST'])]
	public function postFixed(Request $request): Response
	{
		$data = json_decode($request->getContent(), true);
		$userId = $data['user_id'] ?? null;
		$banInfo = $this->banService->getBanInfo($userId);
		if ($banInfo) {
			return new JsonResponse(['reason' => $banInfo->reason], Response::HTTP_FORBIDDEN);
		}
		$this->fixedHolidayErrorHandler->create($userId, $data);
		return new Response(null, 204);
	}

	#[Route('/floating', name: 'post_floating', methods: ['POST'])]
	public function postFloating(Request $request): Response
	{
		$data = json_decode($request->getContent(), true);
		$userId = $data['user_id'] ?? null;
		$banInfo = $this->banService->getBanInfo($userId);
		if ($banInfo) {
			return new JsonResponse(['reason' => $banInfo->reason], Response::HTTP_FORBIDDEN);
		}
		$this->floatingHolidayErrorHandler->create($userId, $data);
		return new Response(null, 204);
	}
}
