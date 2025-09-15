<?php

namespace App\Controller\v1;

use App\Entity\Language;
use App\Service\LoggingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/v1/language', name: 'v1_language_')]
class LanguageControllerV1 extends AbstractController
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly LoggingService         $loggingService)
	{
	}

	#[Route('/', name: 'get_all', methods: ['GET'])]
	public function getAll(Request $request): Response
	{
		$this->loggingService->route($request);
		/**
		 * @var Language[] $languages
		 */
		$languages = $this->entityManager->getRepository(Language::class)
			->findAll();
		$response = new JsonResponse($languages);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}

	#[Route('/{code}', name: 'get_one', methods: ['GET'])]
	public function getOne(Request $request, string $code): Response
	{
		$this->loggingService->route($request);
		/**
		 * @var Language $language
		 */
		$language = $this->entityManager->getRepository(Language::class)
			->findOneBy(['code' => $code]);
		if ($language === null) {
			throw new NotFoundHttpException();
		}
		$response = new JsonResponse($language);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}
}
