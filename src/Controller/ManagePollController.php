<?php

namespace App\Controller;

use App\Entity\Poll;
use App\Entity\PollOption;
use App\Form\PollCreateType;
use App\Repository\PollRepository;
use App\Repository\PollVoteRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/manage/polls', name: 'manage_polls_')]
class ManagePollController extends AbstractController
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly PollRepository         $pollRepository,
		private readonly PollVoteRepository     $pollVoteRepository,
	) {
	}

	#[Route('', name: 'index')]
	public function index(): Response
	{
		$polls = $this->pollRepository->findBy([], ['id' => 'DESC']);
		$voteCounts = [];
		foreach ($polls as $poll) {
			$counts = $this->pollVoteRepository->countByPoll($poll);
			$voteCounts[$poll->id] = array_sum($counts);
		}
		return $this->render('manage/polls/index.html.twig', [
			'polls' => $polls,
			'voteCounts' => $voteCounts,
		]);
	}

	#[Route('/new', name: 'new')]
	public function new(Request $request): Response
	{
		$form = $this->createForm(PollCreateType::class, [
			'start' => new DateTimeImmutable(),
			'end' => new DateTimeImmutable('+7 days'),
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();
			$poll = new Poll(
				$data['name'],
				$data['question'],
				$data['start'],
				$data['end'],
			);

			$optionsText = $form->get('optionsText')->getData();
			$lines = preg_split("/[\r\n]+/", $optionsText ?? '');
			foreach ($lines as $line) {
				$line = trim($line);
				if ($line !== '') {
					$poll->addOption(new PollOption($poll, $line));
				}
			}

			$this->entityManager->persist($poll);
			$this->entityManager->flush();

			return $this->redirectToRoute('manage_polls_show', ['id' => $poll->id]);
		}

		return $this->render('manage/polls/new.html.twig', [
			'form' => $form->createView(),
		]);
	}

	#[Route('/{id}', name: 'show')]
	public function show(int $id): Response
	{
		$poll = $this->pollRepository->find($id);
		if (!$poll) {
			throw $this->createNotFoundException('Poll not found');
		}

		$counts = $this->pollVoteRepository->countByPoll($poll);
		$total = array_sum($counts);

		return $this->render('manage/polls/show.html.twig', [
			'poll' => $poll,
			'counts' => $counts,
			'total' => $total,
		]);
	}
}
