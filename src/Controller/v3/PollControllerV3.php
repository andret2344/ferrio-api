<?php

namespace App\Controller\v3;

use App\Attribute\FirebaseAuth;
use App\Entity\Poll;
use App\Entity\PollOption;
use App\Entity\PollVote;
use App\Repository\PollRepository;
use App\Repository\PollVoteRepository;
use App\Service\BanService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[FirebaseAuth]
#[Route('/v3/polls', name: 'v3_polls_')]
class PollControllerV3 extends AbstractController
{
	public function __construct(
		private readonly PollRepository         $pollRepository,
		private readonly PollVoteRepository     $pollVoteRepository,
		private readonly EntityManagerInterface $entityManager,
		private readonly BanService             $banService,
	)
	{
	}

	#[Route('', name: 'list', methods: ['GET'])]
	public function list(Request $request): JsonResponse
	{
		$userId = $this->getUser()
			->getUserIdentifier();
		$polls = $this->pollRepository->findActive();

		$result = array_map(fn(Poll $poll) => $this->serializePoll($poll, $userId), $polls);

		return $this->json(array_values($result));
	}

	#[Route('/{id}', name: 'get', methods: ['GET'])]
	public function get(int $id): JsonResponse
	{
		$userId = $this->getUser()
			->getUserIdentifier();
		/** @var Poll $poll */
		$poll = $this->pollRepository->find($id);

		if (!$poll || !$poll->isActive()) {
			return $this->json(['error' => 'Poll not found'], Response::HTTP_NOT_FOUND);
		}

		return $this->json($this->serializePoll($poll, $userId));
	}

	#[Route('/{id}/vote', name: 'vote', methods: ['POST'])]
	public function vote(Request $request, int $id): JsonResponse
	{
		$userId = $this->getUser()
			->getUserIdentifier();

		$banInfo = $this->banService->getBanInfo($userId);
		if ($banInfo) {
			return $this->json(['reason' => $banInfo->reason], Response::HTTP_FORBIDDEN);
		}

		/** @var Poll $poll */
		$poll = $this->pollRepository->find($id);
		if (!$poll || !$poll->isActive()) {
			return $this->json(['error' => 'Poll not found'], Response::HTTP_NOT_FOUND);
		}

		$existing = $this->pollVoteRepository->findByUserAndPoll($userId, $poll);
		if ($existing) {
			return $this->json(['error' => 'Already voted'], Response::HTTP_CONFLICT);
		}

		$data = json_decode($request->getContent(), true);
		$optionId = $data['optionId'] ?? null;

		if (!is_int($optionId)) {
			return $this->json(['error' => 'Missing or invalid optionId'], Response::HTTP_BAD_REQUEST);
		}

		$option = null;
		foreach ($poll->options as $o) {
			if ($o->id === $optionId) {
				$option = $o;
				break;
			}
		}

		if (!$option instanceof PollOption) {
			return $this->json(['error' => 'Invalid optionId'], Response::HTTP_BAD_REQUEST);
		}

		$vote = new PollVote($userId, $option, $poll);
		$this->entityManager->persist($vote);
		$this->entityManager->flush();

		return $this->json(null, Response::HTTP_CREATED);
	}

	private function serializePoll(Poll $poll, string $userId): array
	{
		$vote = $this->pollVoteRepository->findByUserAndPoll($userId, $poll);
		$data = $poll->jsonSerialize();
		$data['hasVoted'] = $vote !== null;
		$data['votedOptionId'] = $vote?->option->id;
		return $data;
	}
}
