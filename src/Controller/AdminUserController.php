<?php

namespace App\Controller;

use App\Service\AdminUserService;
use App\Service\BanService;
use App\Service\FirebaseUserLookup;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_users_')]
class AdminUserController extends AbstractController
{
	private const string CSRF_TOKEN_ID = 'user_ban';

	public function __construct(
		private readonly AdminUserService   $users,
		private readonly BanService         $banService,
		private readonly FirebaseUserLookup $firebaseUserLookup,
	)
	{
	}

	#[Route('/bans', name: 'banned', methods: ['GET'])]
	public function banned(): Response
	{
		$bans = $this->banService->findAll();
		$userIds = array_map(fn($ban) => $ban->userId, $bans);

		return $this->render('admin/users/banned.html.twig', [
			'bans' => $bans,
			'profiles' => $this->firebaseUserLookup->lookup($userIds),
			'reportCounts' => $this->users->reportCounts($userIds),
		]);
	}

	#[Route('/api/users/ban', name: 'ban', methods: ['POST'])]
	public function ban(Request $request): JsonResponse
	{
		$data = $this->payload($request);
		if ($data instanceof JsonResponse) {
			return $data;
		}

		$userId = trim((string)($data['user_id'] ?? ''));
		if ($userId === '') {
			return $this->json(['error' => 'User ID is required.', 'field' => 'user_id'], Response::HTTP_BAD_REQUEST);
		}
		$reason = trim((string)($data['reason'] ?? ''));
		if ($reason === '') {
			return $this->json(['error' => 'Reason is required.', 'field' => 'reason'], Response::HTTP_BAD_REQUEST);
		}
		if (mb_strlen($reason) > 2047) {
			return $this->json(['error' => 'Reason must be at most 2047 characters.', 'field' => 'reason'], Response::HTTP_BAD_REQUEST);
		}

		$scope = (string)($data['delete_reports'] ?? 'none');
		if (!in_array($scope, ['none', AdminUserService::SCOPE_PENDING, AdminUserService::SCOPE_ALL], true)) {
			return $this->json(['error' => 'Invalid delete_reports scope.', 'field' => 'delete_reports'], Response::HTTP_BAD_REQUEST);
		}

		$ban = $this->banService->ban($userId, $reason);
		$deleted = 0;
		if ($scope !== 'none') {
			$deleted = $this->users->deleteReports($userId, $scope);
		}

		return $this->json([
			'user_id' => $ban->userId,
			'reason' => $ban->reason,
			'datetime' => $ban->datetime->format('Y-m-d H:i:s'),
			'deleted_reports' => $deleted,
		]);
	}

	#[Route('/api/users/unban', name: 'unban', methods: ['POST'])]
	public function unban(Request $request): JsonResponse
	{
		$data = $this->payload($request);
		if ($data instanceof JsonResponse) {
			return $data;
		}

		$userId = trim((string)($data['user_id'] ?? ''));
		if ($userId === '') {
			return $this->json(['error' => 'User ID is required.', 'field' => 'user_id'], Response::HTTP_BAD_REQUEST);
		}
		if (!$this->banService->unban($userId)) {
			return $this->json(['error' => 'User is not banned.'], Response::HTTP_NOT_FOUND);
		}

		return $this->json(['user_id' => $userId]);
	}

	#[Route('/api/users/reports/delete', name: 'reports_delete', methods: ['POST'])]
	public function deleteReports(Request $request): JsonResponse
	{
		$data = $this->payload($request);
		if ($data instanceof JsonResponse) {
			return $data;
		}

		$userId = trim((string)($data['user_id'] ?? ''));
		if ($userId === '') {
			return $this->json(['error' => 'User ID is required.', 'field' => 'user_id'], Response::HTTP_BAD_REQUEST);
		}
		$scope = (string)($data['scope'] ?? '');
		if (!in_array($scope, [AdminUserService::SCOPE_PENDING, AdminUserService::SCOPE_ALL], true)) {
			return $this->json(['error' => 'Invalid scope.', 'field' => 'scope'], Response::HTTP_BAD_REQUEST);
		}

		return $this->json([
			'user_id' => $userId,
			'scope' => $scope,
			'deleted_reports' => $this->users->deleteReports($userId, $scope),
		]);
	}

	#[Route('/api/users/report-counts', name: 'report_counts', methods: ['GET'])]
	public function reportCounts(Request $request): JsonResponse
	{
		$userId = trim((string)$request->query->get('user_id', ''));
		if ($userId === '') {
			return $this->json(['error' => 'User ID is required.'], Response::HTTP_BAD_REQUEST);
		}
		$counts = $this->users->reportCounts([$userId])[$userId] ?? ['total' => 0, 'pending' => 0];

		return $this->json([
			'user_id' => $userId,
			'total' => $counts['total'],
			'pending' => $counts['pending'],
		]);
	}

	/**
	 * @return array<string, mixed>|JsonResponse the decoded body, or the error response to return as-is
	 */
	private function payload(Request $request): array|JsonResponse
	{
		$data = json_decode($request->getContent(), true);
		if (!is_array($data)) {
			return $this->json(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
		}
		if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string)($data['_token'] ?? ''))) {
			return $this->json(['error' => 'Invalid CSRF token.'], Response::HTTP_BAD_REQUEST);
		}
		return $data;
	}
}
