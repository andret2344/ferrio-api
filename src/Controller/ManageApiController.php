<?php

namespace App\Controller;

use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use App\Entity\Language;
use App\Form\HolidayCreateType;
use App\Form\HolidayUpdateType;
use App\Form\TranslateType;
use App\Handler\CountryLookupTrait;
use App\Repository\FixedHolidayRepository;
use App\Repository\FixedMetadataRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/manage/api', name: 'manage_api_')]
class ManageApiController extends AbstractController
{
	use CountryLookupTrait;

	public function __construct(
		private readonly EntityManagerInterface  $entityManager,
		private readonly FixedHolidayRepository  $fixedHolidayRepository,
		private readonly FixedMetadataRepository $fixedMetadataRepository,
	)
	{
	}

	#[Route('/holiday/update', name: 'holiday_update', methods: ['POST'])]
	public function updateHoliday(Request $request): JsonResponse
	{
		$form = $this->createForm(HolidayUpdateType::class);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();
			$id = $data['metadata_id'];
			$name = $data['name'];
			$desc = $data['description'];
			$mature = (bool)$data['mature'];
			/** @var FixedHoliday $found */
			$found = $this->fixedHolidayRepository->findOneBy(['metadata' => $id, 'language' => 'pl']);
			$found->name = $name;
			$found->description = $desc;
			$found->metadata->matureContent = $mature;
			$this->entityManager->persist($found);
			$this->entityManager->flush();
			return new JsonResponse(['success' => true]);
		}
		$errors = [];
		foreach ($form->getErrors(true) as $error) {
			$errors[] = $error->getMessage();
		}
		return new JsonResponse(['success' => false, 'errors' => $errors], 400);
	}

	#[Route('/holiday', name: 'holiday_create', methods: ['POST'])]
	public function createHoliday(Request $request): JsonResponse
	{
		$repository = $this->entityManager->getRepository(Language::class);
		$language = $repository->findOneBy(['code' => 'pl']);
		if ($language === null) {
			return new JsonResponse(['success' => false, 'errors' => ['Language "pl" not found in the database. Please seed the language table first.']], 400);
		}
		$form = $this->createForm(HolidayCreateType::class);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();
			$month = $data['month'];
			$day = $data['day'];
			$name = $data['name'];
			$desc = $data['description'];
			$country = $this->getCountry($data['country']);
			$mature = (bool)$data['mature'];
			$metadata = new FixedHolidayMetadata($month, $day, 0, $country, null, $mature);
			$this->entityManager->persist($metadata);
			$holiday = new FixedHoliday($language, $metadata, $name, $desc ?? '', '');
			$this->entityManager->persist($holiday);
			$this->entityManager->flush();
			return new JsonResponse([
				'success' => true,
				'id' => $metadata->id,
				'month' => $month,
				'day' => $day,
				'name' => $name,
				'description' => $desc ?? '',
				'countryCode' => $country?->isoCode,
				'countryName' => $country?->englishName,
				'mature' => $mature,
				'message' => sprintf('Holiday "%s" created (ID=%d).', $name, $metadata->id),
			]);
		}
		$errors = [];
		foreach ($form->getErrors(true) as $error) {
			$errors[] = $error->getMessage();
		}
		return new JsonResponse(['success' => false, 'errors' => $errors], 400);
	}

	#[Route('/translation/{to<^\S{2}$>}', name: 'translation_update', methods: ['POST'])]
	public function updateTranslation(Request $request, string $to): JsonResponse
	{
		$form = $this->createForm(TranslateType::class);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$repository = $this->entityManager->getRepository(Language::class);
			$data = $form->getData();
			$id = $data['metadata_id'];
			$name = $data['name'];
			$desc = $data['description'];
			/** @var FixedHolidayMetadata $metadata */
			$metadata = $this->fixedMetadataRepository->findOneBy(['id' => $id]);
			/** @var FixedHoliday|null $holiday */
			$holiday = $this->fixedHolidayRepository->findOneBy(['metadata' => $id, 'language' => $to]);
			/** @var Language $language */
			$language = $repository->findOneBy(['code' => $to]);
			if ($holiday === null) {
				$holiday = new FixedHoliday($language, $metadata, $name, $desc, '');
			} else {
				$holiday->name = $name;
				$holiday->description = $desc;
			}
			$this->entityManager->persist($holiday);
			$this->entityManager->flush();
			return new JsonResponse(['success' => true]);
		}
		$errors = [];
		foreach ($form->getErrors(true) as $error) {
			$errors[] = $error->getMessage();
		}
		return new JsonResponse(['success' => false, 'errors' => $errors], 400);
	}
}
