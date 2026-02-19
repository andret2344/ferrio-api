<?php

namespace App\Controller\v2;

use App\DTO\FixedReportDTO;
use App\DTO\FixedSuggestionDTO;
use App\DTO\FloatingReportDTO;
use App\DTO\FloatingSuggestionDTO;
use App\Handler\ReportHandlerInterface;
use App\Service\BanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/v2/users', name: 'v2_users_')]
class UserControllerV2 extends AbstractController
{
	/** @var ReportHandlerInterface[] */
	private array $handlers;

	/** @var array<string, array<string, class-string>> */
	private array $dtoMap;

	public function __construct(
		private readonly BanService          $banService,
		private readonly SerializerInterface $serializer,
		private readonly ValidatorInterface  $validator,
		ReportHandlerInterface               $floatingSuggestionHandler,
		ReportHandlerInterface               $floatingErrorHandler,
		ReportHandlerInterface               $fixedSuggestionHandler,
		ReportHandlerInterface               $fixedErrorHandler,
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

		$this->dtoMap = [
			'suggestion' => [
				'floating' => FloatingSuggestionDTO::class,
				'fixed' => FixedSuggestionDTO::class,
			],
			'error' => [
				'floating' => FloatingReportDTO::class,
				'fixed' => FixedReportDTO::class,
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

		$dtoClass = $this->dtoMap[$reportType][$holidayType];
		$data = json_decode($request->getContent(), true);
		if (!is_array($data)) {
			return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
		}
		$data['user_id'] = $userId;
		$dto = $this->serializer->denormalize($data, $dtoClass);
		$violations = $this->validator->validate($dto);
		if ($violations->count() > 0) {
			$errors = [];
			foreach ($violations as $violation) {
				$errors[] = [
					'property' => $violation->getPropertyPath(),
					'message' => $violation->getMessage(),
				];
			}
			return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		$handler->create($userId, $dto);
		return $this->json(null, Response::HTTP_CREATED);
	}
}
