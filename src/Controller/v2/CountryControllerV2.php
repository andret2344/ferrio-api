<?php

namespace App\Controller\v2;

use App\Entity\Country;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/v2/countries', name: 'v2_countries_')]
class CountryControllerV2 extends AbstractController
{
	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	#[Route('/', name: 'get_all', methods: ['GET'])]
	public function getAll(Request $request): Response
	{
		$format = (string)$request->query->get('format', '');

		$countries = $this->getCountries($format);
		if ($countries === null) {
			return new JsonResponse(['error' => 'Invalid format, use `code`, `name` or `all`, or skip format'], Response::HTTP_BAD_REQUEST);
		}
		return new JsonResponse($countries);
	}

	/**
	 * @param string $format
	 *
	 * @return Country[]|null
	 */
	private function getCountries(string $format): array|null
	{
		$countries = $this->entityManager->getRepository(Country::class)
			->findAll();
		if ($format === 'code') {
			return array_map(fn(Country $country) => $country->isoCode, $countries);
		}
		if ($format === 'name') {
			return array_map(fn(Country $country) => $country->englishName, $countries);
		}
		if (!$format || $format === 'all') {
			return $countries;
		}
		return null;
	}
}
