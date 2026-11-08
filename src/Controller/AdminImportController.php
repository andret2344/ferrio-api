<?php

namespace App\Controller;

use App\DTO\ImportTile;
use App\Entity\HolidayBlacklistEntry;
use App\Entity\HolidayImportCandidate;
use App\Enum\BlacklistReason;
use App\Enum\ImportCandidateStatus;
use App\Service\Import\HolidayImportService;
use App\Service\Import\HolidayVerifier;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The holiday import page: paste a list from a blog, get persistent tiles for what is not stored yet,
 * and settle each one - verify it on the web, add it, reject it onto the blacklist, or drop the tile.
 *
 * Every tile action answers with the tile re-rendered (`html`), or `html: null` when the tile is gone,
 * so the page swaps it in place and the server stays the only place that decides how a tile looks.
 */
#[Route('/admin', name: 'admin_import')]
class AdminImportController extends AbstractController
{
	public const string CSRF_TOKEN_ID = 'holiday_import';

	/** Generous for a pasted blog page, small enough that a mistaken paste of a whole book is refused. */
	private const int MAX_TEXT_LENGTH = 200_000;

	private const string RESULT_FLASH = 'import_result';

	public function __construct(
		private readonly HolidayImportService   $importService,
		private readonly EntityManagerInterface $entityManager,
	)
	{
	}

	#[Route('/import', name: '', methods: ['GET'])]
	public function index(Request $request): Response
	{
		$result = null;
		$flashes = $request->getSession()->getFlashBag()->get(self::RESULT_FLASH);
		if ($flashes !== []) {
			$result = $flashes[0];
		}

		return $this->render('admin/import/index.html.twig', [
			'tiles' => array_map(self::tileView(...), $this->importService->tiles()),
			'result' => $result,
			'reasons' => BlacklistReason::cases(),
		]);
	}

	#[Route('/import', name: '_paste', methods: ['POST'])]
	public function paste(Request $request): RedirectResponse
	{
		if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string)$request->request->get('_token', ''))) {
			$this->addFlash('error', 'The form expired - paste the text again.');
			return $this->redirectToRoute('admin_import');
		}
		$text = (string)$request->request->get('text', '');
		if (trim($text) === '') {
			$this->addFlash('error', 'Paste some text first.');
			return $this->redirectToRoute('admin_import');
		}
		if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
			$this->addFlash('error', sprintf('The text is too long - paste at most %d characters at once.', self::MAX_TEXT_LENGTH));
			return $this->redirectToRoute('admin_import');
		}

		$this->addFlash(self::RESULT_FLASH, $this->importService->import($text)->toArray());
		return $this->redirectToRoute('admin_import');
	}

	#[Route('/import/blacklist', name: '_blacklist', methods: ['GET'])]
	public function blacklist(): Response
	{
		$entries = $this->entityManager->getRepository(HolidayBlacklistEntry::class)
			->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
		return $this->render('admin/import/blacklist.html.twig', ['entries' => $entries]);
	}

	/**
	 * The tile as it stands now. The page asks for it when the admin comes back from the "Add" tab,
	 * so a tile whose holiday was just stored disappears without a reload.
	 */
	#[Route('/api/import/{id<\d+>}', name: '_show', methods: ['GET'])]
	public function show(int $id): JsonResponse
	{
		$candidate = $this->candidate($id);
		if ($candidate === null) {
			return $this->json(['html' => null]);
		}
		return $this->json(['html' => $this->renderTile($candidate)]);
	}

	#[Route('/api/import/{id<\d+>}/remove', name: '_remove', methods: ['POST'])]
	public function remove(int $id, Request $request): JsonResponse
	{
		$data = $this->payload($request);
		if ($data === null) {
			return $this->json(['error' => 'Invalid CSRF token.'], 403);
		}
		$candidate = $this->candidate($id);
		if ($candidate === null) {
			return $this->json(['error' => 'Tile not found.'], 404);
		}
		$this->importService->remove($candidate);
		return $this->json(['html' => null]);
	}

	#[Route('/api/import/{id<\d+>}/reject', name: '_reject', methods: ['POST'])]
	public function reject(int $id, Request $request): JsonResponse
	{
		$data = $this->payload($request);
		if ($data === null) {
			return $this->json(['error' => 'Invalid CSRF token.'], 403);
		}
		$candidate = $this->candidate($id);
		if ($candidate === null) {
			return $this->json(['error' => 'Tile not found.'], 404);
		}
		$reason = BlacklistReason::tryFrom((string)($data['reason'] ?? ''));
		if ($reason === null) {
			return $this->json(['error' => 'Pick a reason.'], 400);
		}
		try {
			$this->importService->reject($candidate, $reason, (string)($data['comment'] ?? ''));
		} catch (InvalidArgumentException $exception) {
			return $this->json(['error' => $exception->getMessage()], 400);
		}
		return $this->json(['html' => null]);
	}

	#[Route('/api/import/{id<\d+>}/ignore', name: '_ignore', methods: ['POST'])]
	public function ignore(int $id, Request $request): JsonResponse
	{
		$data = $this->payload($request);
		if ($data === null) {
			return $this->json(['error' => 'Invalid CSRF token.'], 403);
		}
		$candidate = $this->candidate($id);
		if ($candidate === null) {
			return $this->json(['error' => 'Tile not found.'], 404);
		}
		try {
			$this->importService->ignoreConflicts($candidate);
		} catch (InvalidArgumentException $exception) {
			return $this->json(['error' => $exception->getMessage()], 400);
		}
		return $this->json(['html' => $this->renderTile($candidate)]);
	}

	#[Route('/api/import/{id<\d+>}/link', name: '_link', methods: ['POST'])]
	public function link(int $id, Request $request): JsonResponse
	{
		$data = $this->payload($request);
		if ($data === null) {
			return $this->json(['error' => 'Invalid CSRF token.'], 403);
		}
		$candidate = $this->candidate($id);
		if ($candidate === null) {
			return $this->json(['error' => 'Tile not found.'], 404);
		}
		$kind = (string)($data['kind'] ?? '');
		$metadataId = filter_var($data['metadata_id'] ?? null, FILTER_VALIDATE_INT);
		if (!in_array($kind, ['fixed', 'floating'], true) || $metadataId === false) {
			return $this->json(['error' => 'Pick a stored holiday.'], 400);
		}
		try {
			$this->importService->link($candidate, $kind, $metadataId);
		} catch (InvalidArgumentException $exception) {
			return $this->json(['error' => $exception->getMessage()], 400);
		}
		return $this->json(['html' => $this->renderTile($candidate)]);
	}

	/**
	 * The verifier is an action argument rather than a constructor dependency: it is the one action
	 * that talks to the Anthropic API, and the rest of the page should not build an HTTP client.
	 */
	#[Route('/api/import/{id<\d+>}/verify', name: '_verify', methods: ['POST'])]
	public function verify(int $id, Request $request, HolidayVerifier $verifier): JsonResponse
	{
		$data = $this->payload($request);
		if ($data === null) {
			return $this->json(['error' => 'Invalid CSRF token.'], 403);
		}
		$candidate = $this->candidate($id);
		if ($candidate === null) {
			return $this->json(['error' => 'Tile not found.'], 404);
		}
		try {
			$verification = $verifier->verify($candidate->name, $candidate->sortedDates());
		} catch (RuntimeException $exception) {
			return $this->json(['error' => $exception->getMessage()], 502);
		}
		$candidate->verify($verification, new DateTimeImmutable());
		$this->entityManager->flush();
		return $this->json(['html' => $this->renderTile($candidate)]);
	}

	#[Route('/api/import/blacklist/{id<\d+>}/restore', name: '_restore', methods: ['POST'])]
	public function restore(int $id, Request $request): JsonResponse
	{
		$data = $this->payload($request);
		if ($data === null) {
			return $this->json(['error' => 'Invalid CSRF token.'], 403);
		}
		$entry = $this->entityManager->getRepository(HolidayBlacklistEntry::class)->find($id);
		if ($entry === null) {
			return $this->json(['error' => 'Entry not found.'], 404);
		}
		$this->importService->restore($entry);
		return $this->json(['ok' => true]);
	}

	/**
	 * @return array<string, mixed>|null the decoded body, or null when its CSRF token is invalid
	 */
	private function payload(Request $request): ?array
	{
		$data = json_decode($request->getContent(), true);
		if (!is_array($data)) {
			$data = [];
		}
		if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string)($data['_token'] ?? ''))) {
			return null;
		}
		return $data;
	}

	private function candidate(int $id): ?HolidayImportCandidate
	{
		return $this->entityManager->getRepository(HolidayImportCandidate::class)->find($id);
	}

	private function renderTile(HolidayImportCandidate $candidate): ?string
	{
		$tile = $this->importService->tile($candidate);
		if ($tile->status === ImportCandidateStatus::RESOLVED) {
			return null;
		}
		return $this->renderView('admin/import/_tile.html.twig', ['tile' => self::tileView($tile)]);
	}

	/**
	 * Everything the tile template needs, worked out here so the page and the JSON actions render a
	 * tile identically.
	 *
	 * @return array<string, mixed>
	 */
	private static function tileView(ImportTile $tile): array
	{
		$candidate = $tile->candidate;
		$primary = $candidate->primaryDate();
		$verification = $candidate->verification;

		$addQuery = ['kind' => 'fixed', 'name' => $candidate->name];
		if ($primary !== null) {
			$addQuery['day'] = $primary['day'];
			$addQuery['month'] = $primary['month'];
		}
		$sourceUrl = self::bestSourceUrl($verification);
		if ($sourceUrl !== null) {
			$addQuery['url'] = $sourceUrl;
		}
		$addFloatingQuery = ['kind' => 'floating', 'name' => $candidate->name];
		if ($sourceUrl !== null) {
			$addFloatingQuery['url'] = $sourceUrl;
		}

		return [
			'id' => $candidate->id,
			'name' => $candidate->name,
			'status' => $tile->status,
			'dates' => $candidate->sortedDates(),
			'conflictingDates' => $tile->conflictingDates,
			'match' => $tile->match,
			'alternatives' => $tile->alternatives,
			'verification' => $verification,
			'verifiedAt' => $candidate->verifiedAt,
			'addQuery' => $addQuery,
			'addFloatingQuery' => $addFloatingQuery,
		];
	}

	/**
	 * The URL to prefill as the new holiday's source: the first one the web search actually returned
	 * that is not another holiday-calendar blog - those are what the import is checking, not evidence.
	 *
	 * @param array<string, mixed>|null $verification
	 */
	private static function bestSourceUrl(?array $verification): ?string
	{
		foreach ($verification['sources'] ?? [] as $source) {
			if (($source['searched'] ?? false) === true && ($source['kind'] ?? '') !== 'calendar_blog') {
				return (string)$source['url'];
			}
		}
		return null;
	}
}
