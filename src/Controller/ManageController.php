<?php

namespace App\Controller;

use App\Entity\Country;
use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use App\Entity\FloatingHoliday;
use App\Entity\Language;
use App\Form\HolidayCheckType;
use App\Form\HolidayCreateType;
use App\Form\HolidayUpdateType;
use App\Form\TranslateType;
use App\Handler\CountryLookupTrait;
use App\Repository\FixedHolidayRepository;
use App\Repository\FixedMetadataRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/manage', name: 'manage_')]
class ManageController extends AbstractController
{
	use CountryLookupTrait;

	public function __construct(
		private readonly EntityManagerInterface  $entityManager,
		private readonly FixedHolidayRepository  $fixedHolidayRepository,
		private readonly FixedMetadataRepository $fixedMetadataRepository)
	{
	}

	#[Route('/', name: 'index')]
	public function index(): Response
	{
		$languages = $this->entityManager->getRepository(Language::class)
			->findBy([], ['code' => 'ASC']);
		return $this->render('manage/index.html.twig', [
			'languages' => $languages
		]);
	}

	#[Route('/translate/{page}/{from<^\S{2}$>}/{to<^\S{2}$>}', name: 'translate')]
	public function translate(Request $request, int $page, string $from, string $to): Response
	{
		$repository = $this->entityManager->getRepository(Language::class);
		if ($request->isMethod('POST') && $request->request->get('action') === 'update') {
			$form = $this->createForm(TranslateType::class);
			$form->handleRequest($request);
			if ($form->isSubmitted() && $form->isValid()) {
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
			}
		}
		$languageFrom = $repository->findOneBy(['code' => $from]);
		$languageTo = $repository->findOneBy(['code' => $to]);
		$languages = $repository->findAll();
		$holidays = $this->fixedHolidayRepository->findAllAggregatedById($from, $to, ($page - 1) * 100, 100);
		$pages = ceil($this->fixedMetadataRepository->count() / 100);
		$forms = [];
		foreach ($holidays as $holiday) {
			$forms[$holiday['id']] = $this->createForm(TranslateType::class, [
				'metadata_id' => $holiday['id'],
				'name' => $holiday['nameTo'],
				'description' => $holiday['descriptionTo'],
			])->createView();
		}
		return $this->render('manage/translate.html.twig', [
			'languageFrom' => $languageFrom,
			'languageTo' => $languageTo,
			'languages' => $languages,
			'holidays' => $holidays,
			'page' => $page,
			'pages' => $pages,
			'forms' => $forms
		]);
	}

	#[Route('/translate/{from<^\S{2}$>}/{to<^\S{2}$>}', name: 'translate_default')]
	public function translateDefault(Request $request, string $from, string $to): Response
	{
		return $this->translate($request, 1, $from, $to);
	}

	#[Route('/create', name: 'create')]
	public function create(Request $request): Response
	{
		return $this->createPage($request, 1);
	}

	#[Route('/create/{page}', name: 'create_page')]
	public function createPage(Request $request, int $page): Response
	{
		$repository = $this->entityManager->getRepository(Language::class);
		/** @var Language $language */
		$language = $repository->findOneBy(['code' => 'pl']);
		$action = $request->request->get('action');
		if ($request->isMethod('POST') && $action === 'update') {
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
			}
		}
		if ($request->isMethod('POST') && $action === 'create') {
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
			}
		}
		$fixedHolidays = $this->fixedHolidayRepository->findAllByLanguage('pl', ($page - 1) * 100, 100, true);
		$floatingHolidays = $this->entityManager->getRepository(FloatingHoliday::class)
			->findBy(['language' => 'pl']);
		$countries = $this->entityManager->getRepository(Country::class)
			->findAll();
		$pages = ceil($this->fixedMetadataRepository->count() / 100);
		$updateForms = [];
		foreach ($fixedHolidays as $holiday) {
			$updateForms[$holiday['id']] = $this->createForm(HolidayUpdateType::class, [
				'metadata_id' => $holiday['id'],
				'name' => $holiday['name'],
				'description' => $holiday['description'],
				'mature' => $holiday['matureContent'],
			])->createView();
		}
		$createForm = $this->createForm(HolidayCreateType::class)->createView();
		return $this->render('manage/create.html.twig', [
			'fixed_holidays' => $fixedHolidays,
			'floating_holidays' => $floatingHolidays,
			'countries' => $countries,
			'page' => $page,
			'pages' => $pages,
			'updateForms' => $updateForms,
			'createForm' => $createForm
		]);
	}

	#[Route('/check', name: 'check')]
	public function check(Request $request): Response
	{
		return $this->checkLanguage($request, 'pl');
	}

	#[Route('/check/{lang<^\S{2}$>}', name: 'check_language')]
	public function checkLanguage(Request $request, string $lang): Response
	{
		$checkForm = $this->createForm(HolidayCheckType::class);
		$result = [];
		if ($request->isMethod('POST') && $request->request->get('action') === 'check') {
			$checkForm->handleRequest($request);
			if ($checkForm->isSubmitted() && $checkForm->isValid()) {
				$data = $checkForm->getData();
				$holidaysText = $data['holidays'];
				if ($holidaysText) {
					$holidays = preg_split("/[\r\n]+/", $holidaysText);
					$result = $this->fixedHolidayRepository->check($lang, $holidays);
				}
			}
		}
		$language = $this->entityManager->getRepository(Language::class)
			->findOneBy(['code' => $lang]);
		$languages = $this->entityManager->getRepository(Language::class)
			->findAll();
		return $this->render('manage/check.html.twig', [
			'language' => $language,
			'languages' => $languages,
			'result' => $result,
			'checkForm' => $checkForm->createView()
		]);
	}

}
