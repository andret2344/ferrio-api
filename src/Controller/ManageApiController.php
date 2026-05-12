<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use App\Entity\FloatingHoliday;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\Language;
use App\Enum\Algorithm;
use App\Handler\CountryLookupTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/api', name: 'admin_api_')]
class ManageApiController extends AbstractController
{
	use CountryLookupTrait;

	public function __construct(
		private readonly EntityManagerInterface $entityManager,
	)
	{
	}

	#[Route('/holiday/{kind<fixed|floating>}', name: 'holiday_create', methods: ['POST'])]
	public function createHoliday(Request $request, string $kind): JsonResponse
	{
		$data = json_decode($request->getContent(), true);
		if (!is_array($data)) {
			return new JsonResponse(['success' => false, 'errors' => ['Invalid JSON body.']], Response::HTTP_BAD_REQUEST);
		}
		if (!$this->isCsrfTokenValid('holiday_save', (string)($data['_token'] ?? ''))) {
			return new JsonResponse(['success' => false, 'errors' => ['Invalid CSRF token.']], Response::HTTP_BAD_REQUEST);
		}

		$source = $data['source'] ?? null;
		if (!is_array($source)) {
			return new JsonResponse(['success' => false, 'errors' => ['Missing source payload.']], Response::HTTP_BAD_REQUEST);
		}
		$sourceName = trim((string)($source['name'] ?? ''));
		if ($sourceName === '') {
			return new JsonResponse(['success' => false, 'errors' => ['Source name (Polish) cannot be empty.']], Response::HTTP_BAD_REQUEST);
		}
		$sourceDescription = $source['description'] ?? null;
		if ($sourceDescription !== null) {
			$sourceDescription = (string)$sourceDescription;
		}

		$polish = $this->entityManager->getRepository(Language::class)
			->findOneBy(['code' => Language::DEFAULT_CODE]);
		if ($polish === null) {
			return new JsonResponse(['success' => false, 'errors' => ['Polish language row missing.']], Response::HTTP_INTERNAL_SERVER_ERROR);
		}

		$meta = $data['metadata'] ?? [];
		$country = $this->getCountry($meta['country'] ?? null);
		$mature = !empty($meta['mature']);

		if ($kind === 'fixed') {
			$month = filter_var($meta['month'] ?? null, FILTER_VALIDATE_INT);
			$day = filter_var($meta['day'] ?? null, FILTER_VALIDATE_INT);
			if ($month === false || $month < 1 || $month > 12) {
				return new JsonResponse(['success' => false, 'errors' => ['Month must be between 1 and 12.']], Response::HTTP_BAD_REQUEST);
			}
			if ($day === false || $day < 1 || $day > 31) {
				return new JsonResponse(['success' => false, 'errors' => ['Day must be between 1 and 31.']], Response::HTTP_BAD_REQUEST);
			}
			$metadata = new FixedHolidayMetadata($month, $day, false, $country, [], $mature);
		} else {
			$algorithm = Algorithm::tryFrom((string)($meta['algorithm'] ?? ''));
			if ($algorithm === null) {
				return new JsonResponse(['success' => false, 'errors' => ['Invalid algorithm.']], Response::HTTP_BAD_REQUEST);
			}
			$argsString = null;
			$rawArgs = $meta['algorithm_args'] ?? null;
			if ($rawArgs !== null && !(is_string($rawArgs) && trim($rawArgs) === '')) {
				if (!is_string($rawArgs)) {
					return new JsonResponse(['success' => false, 'errors' => ['Algorithm args must be sent as a raw JSON string.']], Response::HTTP_BAD_REQUEST);
				}
				$argsString = trim($rawArgs);
				$decoded = json_decode($argsString, true);
				if (!is_array($decoded) || array_is_list($decoded)) {
					return new JsonResponse(['success' => false, 'errors' => ['Algorithm args must be a JSON object.']], Response::HTTP_BAD_REQUEST);
				}
			}
			$metadata = new FloatingHolidayMetadata(false, $country, [], null, '[]', $mature, $algorithm, $argsString);
		}

		$tagIds = array_values(array_filter(array_map('intval', $meta['tags'] ?? []), fn(int $v) => $v > 0));
		if ($tagIds !== []) {
			$categories = $this->entityManager->getRepository(Category::class)->findBy(['id' => $tagIds]);
			$metadata->categories = new ArrayCollection($categories);
		}

		$this->entityManager->persist($metadata);

		$polishHoliday = $kind === 'fixed'
			? new FixedHoliday($polish, $metadata, $sourceName, $sourceDescription, '')
			: new FloatingHoliday($polish, $metadata, $sourceName, $sourceDescription, '');
		$this->entityManager->persist($polishHoliday);

		$languageRepo = $this->entityManager->getRepository(Language::class);
		$translations = $data['translations'] ?? [];
		if (!is_array($translations)) {
			$translations = [];
		}
		$errors = [];
		foreach ($translations as $code => $entry) {
			if (!is_string($code) || $code === Language::DEFAULT_CODE) {
				$errors[] = sprintf('Invalid language code "%s".', (string)$code);
				continue;
			}
			if (!is_array($entry)) {
				$errors[] = sprintf('Invalid payload for language "%s".', $code);
				continue;
			}
			$language = $languageRepo->findOneBy(['code' => $code]);
			if ($language === null) {
				$errors[] = sprintf('Unknown language "%s".', $code);
				continue;
			}
			$name = trim((string)($entry['name'] ?? ''));
			$description = $entry['description'] ?? null;
			if ($description !== null) {
				$description = (string)$description;
			}
			if ($name === '' && (string)$description === '') {
				continue;
			}
			$holiday = $kind === 'fixed'
				? new FixedHoliday($language, $metadata, $name, $description, '')
				: new FloatingHoliday($language, $metadata, $name, $description, '');
			$this->entityManager->persist($holiday);
		}

		if ($errors !== []) {
			return new JsonResponse(['success' => false, 'errors' => $errors], Response::HTTP_BAD_REQUEST);
		}

		$this->entityManager->flush();

		return new JsonResponse([
			'success' => true,
			'id' => $metadata->id,
			'redirect' => $this->generateUrl('admin_holiday_detail', ['kind' => $kind, 'id' => $metadata->id]),
		]);
	}

	#[Route('/holiday/{kind<fixed|floating>}/{id<\d+>}', name: 'holiday_save', methods: ['POST'])]
	public function saveHoliday(Request $request, string $kind, int $id): JsonResponse
	{
		$data = json_decode($request->getContent(), true);
		if (!is_array($data)) {
			return new JsonResponse(['success' => false, 'errors' => ['Invalid JSON body.']], Response::HTTP_BAD_REQUEST);
		}

		if (!$this->isCsrfTokenValid('holiday_save', (string)($data['_token'] ?? ''))) {
			return new JsonResponse(['success' => false, 'errors' => ['Invalid CSRF token.']], Response::HTTP_BAD_REQUEST);
		}

		$metadataClass = $kind === 'fixed' ? FixedHolidayMetadata::class : FloatingHolidayMetadata::class;
		$holidayClass = $kind === 'fixed' ? FixedHoliday::class : FloatingHoliday::class;

		$metadata = $this->entityManager->getRepository($metadataClass)->find($id);
		if ($metadata === null) {
			return new JsonResponse(['success' => false, 'errors' => ['Holiday not found.']], Response::HTTP_NOT_FOUND);
		}

		$languageRepo = $this->entityManager->getRepository(Language::class);
		$holidayRepo = $this->entityManager->getRepository($holidayClass);

		$source = $data['source'] ?? null;
		if (!is_array($source)) {
			return new JsonResponse(['success' => false, 'errors' => ['Missing source payload.']], Response::HTTP_BAD_REQUEST);
		}
		$sourceName = trim((string)($source['name'] ?? ''));
		if ($sourceName === '') {
			return new JsonResponse(['success' => false, 'errors' => ['Source name (Polish) cannot be empty.']], Response::HTTP_BAD_REQUEST);
		}
		$sourceDescription = $source['description'] ?? null;
		if ($sourceDescription !== null) {
			$sourceDescription = (string)$sourceDescription;
		}

		$polish = $languageRepo->findOneBy(['code' => Language::DEFAULT_CODE]);
		if ($polish === null) {
			return new JsonResponse(['success' => false, 'errors' => ['Polish language row missing.']], Response::HTTP_INTERNAL_SERVER_ERROR);
		}
		$polishHoliday = $holidayRepo->findOneBy(['metadata' => $id, 'language' => Language::DEFAULT_CODE]);
		if ($polishHoliday === null) {
			$polishHoliday = $kind === 'fixed'
				? new FixedHoliday($polish, $metadata, $sourceName, $sourceDescription, '')
				: new FloatingHoliday($polish, $metadata, $sourceName, $sourceDescription, '');
		} else {
			$polishHoliday->name = $sourceName;
			$polishHoliday->description = $sourceDescription;
		}
		$this->entityManager->persist($polishHoliday);

		$meta = $data['metadata'] ?? [];
		$metadata->matureContent = !empty($meta['mature']);
		$metadata->country = $this->getCountry($meta['country'] ?? null);
		if ($kind === 'fixed') {
			/** @var FixedHolidayMetadata $metadata */
			$month = filter_var($meta['month'] ?? null, FILTER_VALIDATE_INT);
			$day = filter_var($meta['day'] ?? null, FILTER_VALIDATE_INT);
			if ($month === false || $month < 1 || $month > 12) {
				return new JsonResponse(['success' => false, 'errors' => ['Month must be between 1 and 12.']], Response::HTTP_BAD_REQUEST);
			}
			if ($day === false || $day < 1 || $day > 31) {
				return new JsonResponse(['success' => false, 'errors' => ['Day must be between 1 and 31.']], Response::HTTP_BAD_REQUEST);
			}
			$metadata->month = $month;
			$metadata->day = $day;
		} else {
			/** @var FloatingHolidayMetadata $metadata */
			$algorithm = \App\Enum\Algorithm::tryFrom((string)($meta['algorithm'] ?? ''));
			if ($algorithm === null) {
				return new JsonResponse(['success' => false, 'errors' => ['Invalid algorithm.']], Response::HTTP_BAD_REQUEST);
			}
			$metadata->algorithm = $algorithm;

			$rawArgs = $meta['algorithm_args'] ?? null;
			if ($rawArgs === null || (is_string($rawArgs) && trim($rawArgs) === '')) {
				$metadata->algorithmArgs = null;
			} else {
				if (!is_string($rawArgs)) {
					return new JsonResponse(['success' => false, 'errors' => ['Algorithm args must be sent as a raw JSON string.']], Response::HTTP_BAD_REQUEST);
				}
				$argsString = trim($rawArgs);
				$decoded = json_decode($argsString, true);
				if (!is_array($decoded) || array_is_list($decoded)) {
					return new JsonResponse(['success' => false, 'errors' => ['Algorithm args must be a JSON object.']], Response::HTTP_BAD_REQUEST);
				}
				$metadata->algorithmArgs = $argsString;
			}
		}

		$tagIds = array_values(array_filter(array_map('intval', $meta['tags'] ?? []), fn(int $v) => $v > 0));
		$categories = $tagIds === []
			? []
			: $this->entityManager->getRepository(Category::class)
				->findBy(['id' => $tagIds]);
		$metadata->categories = new ArrayCollection($categories);

		$translations = $data['translations'] ?? [];
		if (!is_array($translations)) {
			$translations = [];
		}
		$errors = [];
		foreach ($translations as $code => $entry) {
			if (!is_string($code) || $code === Language::DEFAULT_CODE) {
				$errors[] = sprintf('Invalid language code "%s".', (string)$code);
				continue;
			}
			if (!is_array($entry)) {
				$errors[] = sprintf('Invalid payload for language "%s".', $code);
				continue;
			}
			$language = $languageRepo->findOneBy(['code' => $code]);
			if ($language === null) {
				$errors[] = sprintf('Unknown language "%s".', $code);
				continue;
			}
			$name = trim((string)($entry['name'] ?? ''));
			$description = $entry['description'] ?? null;
			if ($description !== null) {
				$description = (string)$description;
			}

			$holiday = $holidayRepo->findOneBy(['metadata' => $id, 'language' => $code]);
			if ($name === '' && (string)$description === '') {
				if ($holiday !== null) {
					$this->entityManager->remove($holiday);
				}
				continue;
			}
			if ($holiday === null) {
				$holiday = $kind === 'fixed'
					? new FixedHoliday($language, $metadata, $name, $description, '')
					: new FloatingHoliday($language, $metadata, $name, $description, '');
			} else {
				$holiday->name = $name;
				$holiday->description = $description;
			}
			$this->entityManager->persist($holiday);
		}

		if ($errors !== []) {
			return new JsonResponse(['success' => false, 'errors' => $errors], Response::HTTP_BAD_REQUEST);
		}

		$this->entityManager->flush();

		return new JsonResponse(['success' => true]);
	}
}
