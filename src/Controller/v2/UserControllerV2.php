<?php

namespace App\Controller\v2;

use App\Handler\ReportHandlerInterface;
use App\Service\BanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/v2/users', name: 'v2_users_')]
class UserControllerV2 extends AbstractController
{
	/** @var ReportHandlerInterface */
	private array $handlers;

	public function __construct(
		private readonly BanService $banService,
		ReportHandlerInterface      $floatingSuggestionHandler,
		ReportHandlerInterface      $floatingErrorHandler,
		ReportHandlerInterface      $fixedSuggestionHandler,
		ReportHandlerInterface      $fixedErrorHandler,
	)
	{
		$this->handlers = [
			'suggestion' => [
				'floating' => $floatingSuggestionHandler,
				'fixed' => $fixedSuggestionHandler,
			],
			'error' => [
				'floating' => $floatingErrorHandler,
				'fixed' => $fixedErrorHandler,
			],
		];
	}

	#[Route('/{userId<^\S+$>}/reports', name: 'reports', methods: ['GET', 'POST'])]
	public function handle(string $userId, Request $request): JsonResponse
	{
		$banInfo = $this->banService->getBanInfo($userId);
		if ($banInfo) {
			return new JsonResponse(['reason' => $banInfo->reason], Response::HTTP_FORBIDDEN);
		}

		$reportType = (string)$request->query->get('reportType', '');
		$holidayType = (string)$request->query->get('holidayType', '');

		$handler = $this->handlers[$reportType][$holidayType] ?? null;
		if (!$handler) {
			throw new BadRequestHttpException('Invalid reportType or holidayType');
		}

		if ($request->isMethod('GET')) {
			return $this->json($handler->list($userId));
		}

		$payload = (array)json_decode($request->getContent(), true);
		$handler->create($userId, $payload);
		return $this->json(null, Response::HTTP_CREATED);
	}
}
