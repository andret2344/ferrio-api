<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Country;
use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayError;
use App\Entity\FixedHolidayMetadata;
use App\Entity\FixedHolidaySuggestion;
use App\Entity\FloatingHoliday;
use App\Entity\FloatingHolidayError;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\FloatingHolidaySuggestion;
use App\Entity\Language;
use App\Entity\Poll;
use App\Entity\ReportState;
use App\Form\HolidayCreateType;
use App\Form\TranslateType;
use App\Repository\FixedHolidayRepository;
use App\Service\FirebaseUserLookup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
class ManageController extends AbstractController
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly FixedHolidayRepository $fixedHolidayRepository,
		private readonly FirebaseUserLookup     $firebaseUserLookup,
	)
	{
	}

	#[Route('/', name: 'index')]
	public function index(): Response
	{
		$languages = $this->entityManager->getRepository(Language::class)
			->findBy([], ['code' => 'ASC']);
		$fixedCount = $this->entityManager->getRepository(FixedHolidayMetadata::class)
			->count([]);
		$floatingCount = $this->entityManager->getRepository(FloatingHolidayMetadata::class)
			->count([]);
		$tagCount = $this->entityManager->getRepository(Category::class)
			->count([]);
		$translationCounts = $this->countTranslationsByLanguage();
		$translationTotal = $translationCounts['pl'] ?? 0;
		$reportCounts = [
			'fixed_suggestion' => $this->countByReportState(FixedHolidaySuggestion::class),
			'floating_suggestion' => $this->countByReportState(FloatingHolidaySuggestion::class),
			'fixed_error' => $this->countByReportState(FixedHolidayError::class),
			'floating_error' => $this->countByReportState(FloatingHolidayError::class),
		];
		$pollCount = $this->entityManager->getRepository(Poll::class)
			->count([]);
		return $this->render('admin/index.html.twig', [
			'languages' => $languages,
			'fixedHolidayCount' => $fixedCount,
			'floatingHolidayCount' => $floatingCount,
			'tagCount' => $tagCount,
			'translationCounts' => $translationCounts,
			'translationTotal' => $translationTotal,
			'reportCounts' => $reportCounts,
			'pollCount' => $pollCount,
		]);
	}

	/**
	 * @param class-string $entityClass
	 * @return array{reported: int, total: int}
	 */
	private function countByReportState(string $entityClass): array
	{
		$repo = $this->entityManager->getRepository($entityClass);
		return [
			'reported' => $repo->count(['reportState' => ReportState::REPORTED]),
			'total' => $repo->count([]),
		];
	}

	/**
	 * @return array<string, int> map of language code => total translated holidays (fixed + floating)
	 */
	private function countTranslationsByLanguage(): array
	{
		$counts = [];
		foreach ([FixedHoliday::class, FloatingHoliday::class] as $entityClass) {
			$rows = $this->entityManager->createQueryBuilder()
				->select('IDENTITY(h.language) AS code, COUNT(h.name) AS cnt')
				->from($entityClass, 'h')
				->groupBy('h.language')
				->getQuery()
				->getResult();
			foreach ($rows as $row) {
				$counts[$row['code']] = ($counts[$row['code']] ?? 0) + (int)$row['cnt'];
			}
		}
		return $counts;
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
		return $this->render('admin/translate.html.twig', [
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
	public function create(): Response
	{
		return $this->redirectToRoute('admin_create_month', ['month' => (int)date('m')]);
	}

	#[Route('/create/{month<^([1-9]|1[0-2])$>}', name: 'create_month')]
	public function createMonth(int $month): Response
	{
		$fixedHolidays = $this->fixedHolidayRepository->findAllByLanguage('pl', matureContent: true, month: $month);
		$floatingHolidays = $this->entityManager->getRepository(FloatingHoliday::class)
			->findBy(['language' => 'pl']);
		$countries = $this->entityManager->getRepository(Country::class)
			->findAll();
		$tags = $this->entityManager->getRepository(Category::class)
			->findBy([], ['slug' => 'ASC']);
		$tagsByMetadata = [];
		if (!empty($fixedHolidays)) {
			$metadataIds = array_column($fixedHolidays, 'id');
			/** @var FixedHolidayMetadata[] $metadatas */
			$metadatas = $this->entityManager->getRepository(FixedHolidayMetadata::class)
				->createQueryBuilder('m')
				->leftJoin('m.categories', 'c')
				->addSelect('c')
				->where('m.id IN (:ids)')
				->setParameter('ids', $metadataIds)
				->getQuery()
				->getResult();
			foreach ($metadatas as $m) {
				$tagsByMetadata[$m->id] = array_values(array_map(
					fn(Category $c) => $c->id,
					$m->categories->toArray()
				));
			}
		}
		$createForm = $this->createForm(HolidayCreateType::class)
			->createView();
		return $this->render('admin/create.html.twig', [
			'fixed_holidays' => $fixedHolidays,
			'floating_holidays' => $floatingHolidays,
			'countries' => $countries,
			'tags' => $tags,
			'tags_by_metadata' => $tagsByMetadata,
			'month' => $month,
			'createForm' => $createForm
		]);
	}

	#[Route('/floating', name: 'floating')]
	public function floating(): Response
	{
		$polishHolidays = $this->entityManager->getRepository(FloatingHoliday::class)
			->createQueryBuilder('h')
			->select('h', 'm')
			->join('h.metadata', 'm')
			->leftJoin('m.country', 'c')
			->addSelect('c')
			->leftJoin('m.categories', 'cat')
			->addSelect('cat')
			->where('h.language = :language')
			->setParameter('language', 'pl')
			->orderBy('m.algorithm', 'ASC')
			->addOrderBy('h.name', 'ASC')
			->getQuery()
			->getResult();
		return $this->render('admin/floating.html.twig', [
			'floating_holidays' => $polishHolidays,
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

		$fixedMetadataIds = array_values(array_unique(array_filter(array_merge(
			array_map(fn($s) => $s->holiday?->id, $fixedSuggestions),
			array_map(fn($e) => $e->metadata?->id, $fixedErrors),
		))));
		$floatingMetadataIds = array_values(array_unique(array_filter(array_merge(
			array_map(fn($s) => $s->holiday?->id, $floatingSuggestions),
			array_map(fn($e) => $e->metadata?->id, $floatingErrors),
		))));

		$fixedHolidaysPl = [];
		$tagsByFixedMetadata = [];
		$fixedMetadataInfo = [];
		if (!empty($fixedMetadataIds)) {
			/** @var FixedHoliday[] $rows */
			$rows = $this->entityManager->getRepository(FixedHoliday::class)
				->createQueryBuilder('h')
				->where('h.language = :lang')
				->andWhere('h.metadata IN (:ids)')
				->setParameter('lang', 'pl')
				->setParameter('ids', $fixedMetadataIds)
				->getQuery()
				->getResult();
			foreach ($rows as $h) {
				$fixedHolidaysPl[$h->metadata->id] = [
					'name' => $h->name,
					'description' => $h->description,
				];
			}
			/** @var FixedHolidayMetadata[] $metadatas */
			$metadatas = $this->entityManager->getRepository(FixedHolidayMetadata::class)
				->createQueryBuilder('m')
				->leftJoin('m.categories', 'c')
				->addSelect('c')
				->where('m.id IN (:ids)')
				->setParameter('ids', $fixedMetadataIds)
				->getQuery()
				->getResult();
			foreach ($metadatas as $m) {
				$tagsByFixedMetadata[$m->id] = array_values(array_map(
					fn(Category $c) => $c->id,
					$m->categories->toArray()
				));
				$fixedMetadataInfo[$m->id] = [
					'day' => $m->day,
					'month' => $m->month,
					'country' => $m->country?->isoCode,
					'mature' => $m->matureContent,
				];
			}
		}

		$floatingHolidaysPl = [];
		if (!empty($floatingMetadataIds)) {
			/** @var FloatingHoliday[] $rows */
			$rows = $this->entityManager->getRepository(FloatingHoliday::class)
				->createQueryBuilder('h')
				->where('h.language = :lang')
				->andWhere('h.metadata IN (:ids)')
				->setParameter('lang', 'pl')
				->setParameter('ids', $floatingMetadataIds)
				->getQuery()
				->getResult();
			foreach ($rows as $h) {
				$floatingHolidaysPl[$h->metadata->id] = [
					'name' => $h->name,
					'description' => $h->description,
				];
			}
		}

		$countries = $this->entityManager->getRepository(Country::class)->findAll();
		$tags = $this->entityManager->getRepository(Category::class)
			->findBy([], ['slug' => 'ASC']);

		return $this->render('admin/reports.html.twig', [
			'fixedSuggestions' => $fixedSuggestions,
			'floatingSuggestions' => $floatingSuggestions,
			'fixedErrors' => $fixedErrors,
			'floatingErrors' => $floatingErrors,
			'users' => $users,
			'report_states' => array_column(ReportState::cases(), 'value'),
			'fixedHolidaysPl' => $fixedHolidaysPl,
			'floatingHolidaysPl' => $floatingHolidaysPl,
			'tagsByFixedMetadata' => $tagsByFixedMetadata,
			'fixedMetadataInfo' => $fixedMetadataInfo,
			'countries' => $countries,
			'tags' => $tags,
		]);
	}

	#[Route('/reports/moderate', name: 'reports_moderate', methods: ['POST'])]
	public function moderateReport(Request $request): JsonResponse
	{
		$data = json_decode($request->getContent(), true);
		if (!is_array($data)) {
			throw new BadRequestHttpException('Invalid JSON body');
		}

		$kind = $data['kind'] ?? null;
		$entityClass = match ($kind) {
			'fixed_suggestion' => FixedHolidaySuggestion::class,
			'floating_suggestion' => FloatingHolidaySuggestion::class,
			'fixed_error' => FixedHolidayError::class,
			'floating_error' => FloatingHolidayError::class,
			default => throw new BadRequestHttpException('Invalid kind'),
		};
		$metadataClass = match ($kind) {
			'fixed_suggestion', 'fixed_error' => FixedHolidayMetadata::class,
			'floating_suggestion', 'floating_error' => FloatingHolidayMetadata::class,
		};
		$relationField = match ($kind) {
			'fixed_suggestion', 'floating_suggestion' => 'holiday',
			'fixed_error', 'floating_error' => 'metadata',
		};

		$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
		if ($id === false) {
			throw new BadRequestHttpException('Invalid id');
		}

		$state = ReportState::tryFrom((string)($data['report_state'] ?? ''));
		if (!$state) {
			throw new BadRequestHttpException('Invalid report_state');
		}

		$comment = $data['comment'] ?? null;
		if ($comment !== null) {
			$comment = trim((string)$comment);
			if ($comment === '') {
				$comment = null;
			}
		}

		$holidayIdRaw = $data['holiday_id'] ?? null;
		if ($holidayIdRaw === '' || $holidayIdRaw === null) {
			$holidayId = null;
		} else {
			$holidayId = filter_var($holidayIdRaw, FILTER_VALIDATE_INT);
			if ($holidayId === false || $holidayId <= 0) {
				return $this->json(['error' => 'Invalid holiday id', 'field' => 'holiday_id'], Response::HTTP_BAD_REQUEST);
			}
			if ($this->entityManager->getRepository($metadataClass)->find($holidayId) === null) {
				return $this->json(['error' => 'Holiday does not exist', 'field' => 'holiday_id'], Response::HTTP_BAD_REQUEST);
			}
		}

		$affected = $this->entityManager->createQueryBuilder()
			->update($entityClass, 'r')
			->set('r.reportState', ':state')
			->set('r.comment', ':comment')
			->set("r.$relationField", ':holiday')
			->where('r.id = :id')
			->setParameter('state', $state)
			->setParameter('comment', $comment)
			->setParameter('holiday', $holidayId)
			->setParameter('id', $id)
			->getQuery()
			->execute();

		if ($affected === 0) {
			return $this->json(['error' => 'Report not found'], Response::HTTP_NOT_FOUND);
		}

		return $this->json([
			'id' => $id,
			'report_state' => $state->value,
			'comment' => $comment,
			'holiday_id' => $holidayId,
		]);
	}

	#[Route('/reports/delete', name: 'reports_delete', methods: ['POST'])]
	public function deleteReport(Request $request): JsonResponse
	{
		$data = json_decode($request->getContent(), true);
		if (!is_array($data)) {
			throw new BadRequestHttpException('Invalid JSON body');
		}

		$kind = $data['kind'] ?? null;
		$entityClass = match ($kind) {
			'fixed_suggestion' => FixedHolidaySuggestion::class,
			'floating_suggestion' => FloatingHolidaySuggestion::class,
			'fixed_error' => FixedHolidayError::class,
			'floating_error' => FloatingHolidayError::class,
			default => throw new BadRequestHttpException('Invalid kind'),
		};

		$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
		if ($id === false) {
			throw new BadRequestHttpException('Invalid id');
		}

		$affected = $this->entityManager->createQueryBuilder()
			->delete($entityClass, 'r')
			->where('r.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->execute();

		if ($affected === 0) {
			return $this->json(['error' => 'Report not found'], Response::HTTP_NOT_FOUND);
		}

		return $this->json(['id' => $id]);
	}

}
