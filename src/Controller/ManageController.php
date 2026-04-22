<?php

namespace App\Controller;

use App\Entity\Country;
use App\Entity\FixedHolidayError;
use App\Entity\FixedHolidaySuggestion;
use App\Entity\FloatingHoliday;
use App\Entity\FloatingHolidayError;
use App\Entity\FloatingHolidaySuggestion;
use App\Entity\Language;
use App\Form\HolidayCheckType;
use App\Form\HolidayCreateType;
use App\Form\HolidayUpdateType;
use App\Form\TranslateType;
use App\Repository\FixedHolidayRepository;
use App\Repository\FixedMetadataRepository;
use App\Service\FirebaseUserLookup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/manage', name: 'manage_')]
class ManageController extends AbstractController
{
	public function __construct(
		private readonly EntityManagerInterface  $entityManager,
		private readonly FixedHolidayRepository  $fixedHolidayRepository,
		private readonly FixedMetadataRepository $fixedMetadataRepository,
		private readonly FirebaseUserLookup      $firebaseUserLookup,
	)
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

	#[Route('/translate/{month<^([1-9]|1[0-2])$>}', name: 'translate')]
	public function translate(Request $request, int $month): Response
	{
		$from = $request->query->getString('from', 'pl');
		$to = $request->query->getString('to', 'en');
		$repository = $this->entityManager->getRepository(Language::class);
		$languageFrom = $repository->findOneBy(['code' => $from]);
		$languageTo = $repository->findOneBy(['code' => $to]);
		$languages = $repository->findAll();
		$holidays = $this->fixedHolidayRepository->findAllAggregatedById($from, $to, $month);
		$forms = [];
		foreach ($holidays as $holiday) {
			$forms[$holiday['id']] = $this->createForm(TranslateType::class, [
				'metadata_id' => $holiday['id'],
				'name' => $holiday['nameTo'],
				'description' => $holiday['descriptionTo'],
			])
				->createView();
		}
		return $this->render('manage/translate.html.twig', [
			'languageFrom' => $languageFrom,
			'languageTo' => $languageTo,
			'languages' => $languages,
			'holidays' => $holidays,
			'month' => $month,
			'forms' => $forms
		]);
	}

	#[Route('/translate', name: 'translate_default')]
	public function translateDefault(Request $request): Response
	{
		return $this->translate($request, 1);
	}

	#[Route('/create', name: 'create')]
	public function create(Request $request): Response
	{
		return $this->createMonth($request, (int) date('n'));
	}

	#[Route('/create/{month<^([1-9]|1[0-2])$>}', name: 'create_month')]
	public function createMonth(Request $request, int $month): Response
	{
		$fixedHolidays = $this->fixedHolidayRepository->findAllByLanguage('pl', matureContent: true, month: $month);
		$floatingHolidays = $this->entityManager->getRepository(FloatingHoliday::class)
			->findBy(['language' => 'pl']);
		$countries = $this->entityManager->getRepository(Country::class)
			->findAll();
		$updateForms = [];
		foreach ($fixedHolidays as $holiday) {
			$updateForms[$holiday['id']] = $this->createForm(HolidayUpdateType::class, [
				'metadata_id' => $holiday['id'],
				'name' => $holiday['name'],
				'description' => $holiday['description'],
				'mature' => $holiday['matureContent'],
			])
				->createView();
		}
		$createForm = $this->createForm(HolidayCreateType::class)
			->createView();
		return $this->render('manage/create.html.twig', [
			'fixed_holidays' => $fixedHolidays,
			'floating_holidays' => $floatingHolidays,
			'countries' => $countries,
			'month' => $month,
			'updateForms' => $updateForms,
			'createForm' => $createForm
		]);
	}

	#[Route('/reports', name: 'reports')]
	public function reports(): Response
	{
		$fixedSuggestions = $this->entityManager->getRepository(FixedHolidaySuggestion::class)
			->findBy([], ['datetime' => 'DESC']);
		$floatingSuggestions = $this->entityManager->getRepository(FloatingHolidaySuggestion::class)
			->findBy([], ['datetime' => 'DESC']);
		$fixedErrors = $this->entityManager->getRepository(FixedHolidayError::class)
			->findBy([], ['datetime' => 'DESC']);
		$floatingErrors = $this->entityManager->getRepository(FloatingHolidayError::class)
			->findBy([], ['datetime' => 'DESC']);

		$uids = array_merge(
			array_map(fn($s) => $s->userId, $fixedSuggestions),
			array_map(fn($s) => $s->userId, $floatingSuggestions),
			array_map(fn($e) => $e->userId, $fixedErrors),
			array_map(fn($e) => $e->userId, $floatingErrors),
		);
		$users = $this->firebaseUserLookup->lookup($uids);

		return $this->render('manage/reports.html.twig', [
			'fixedSuggestions' => $fixedSuggestions,
			'floatingSuggestions' => $floatingSuggestions,
			'fixedErrors' => $fixedErrors,
			'floatingErrors' => $floatingErrors,
			'users' => $users,
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
