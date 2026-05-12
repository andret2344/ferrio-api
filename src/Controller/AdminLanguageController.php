<?php

namespace App\Controller;

use App\Entity\CategoryTranslation;
use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayError;
use App\Entity\FloatingHoliday;
use App\Entity\FloatingHolidayError;
use App\Entity\Language;
use App\Service\AdminMetricsService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/languages', name: 'admin_languages_')]
class AdminLanguageController extends AbstractController
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly AdminMetricsService    $metrics,
	)
	{
	}

	#[Route('', name: 'index', methods: ['GET'])]
	public function index(): Response
	{
		$languages = $this->entityManager->getRepository(Language::class)
			->findBy([], ['code' => 'ASC']);

		return $this->render('admin/languages/index.html.twig', [
			'languages' => $languages,
			'translationCounts' => $this->metrics->translationCountsByLanguage(),
			'defaultCode' => Language::DEFAULT_CODE,
		]);
	}

	#[Route('', name: 'create', methods: ['POST'])]
	public function create(Request $request): RedirectResponse
	{
		$code = strtolower(trim((string)$request->request->get('code', '')));
		$name = trim((string)$request->request->get('name', ''));

		if ($code === '' || $name === '') {
			$this->addFlash('error', 'Code and name are both required.');
			return $this->redirectToRoute('admin_languages_index');
		}
		if (!preg_match('/^[a-z][a-z0-9-]{1,30}$/', $code)) {
			$this->addFlash('error', 'Code must be 2–31 chars, lowercase letters, digits or hyphens, starting with a letter.');
			return $this->redirectToRoute('admin_languages_index');
		}
		if (mb_strlen($name) > 63) {
			$this->addFlash('error', 'Name must be at most 63 characters.');
			return $this->redirectToRoute('admin_languages_index');
		}

		$existing = $this->entityManager->getRepository(Language::class)->find($code);
		if ($existing !== null) {
			$this->addFlash('error', sprintf('Language "%s" already exists.', $code));
			return $this->redirectToRoute('admin_languages_index');
		}

		$this->entityManager->persist(new Language($code, $name));
		try {
			$this->entityManager->flush();
			$this->addFlash('success', sprintf('Language "%s" created.', $code));
		} catch (UniqueConstraintViolationException) {
			$this->addFlash('error', sprintf('Language name "%s" is already used.', $name));
		}

		return $this->redirectToRoute('admin_languages_index');
	}

	#[Route('/{code}/delete', name: 'delete', methods: ['POST'])]
	public function delete(string $code, Request $request): RedirectResponse
	{
		$language = $this->entityManager->getRepository(Language::class)->find($code);
		if ($language === null) {
			$this->addFlash('error', 'Language not found.');
			return $this->redirectToRoute('admin_languages_index');
		}
		if ($language->code === Language::DEFAULT_CODE) {
			$this->addFlash('error', sprintf('Cannot delete the source language "%s".', $language->code));
			return $this->redirectToRoute('admin_languages_index');
		}

		$confirmation = trim((string)$request->request->get('confirm', ''));
		if ($confirmation !== $language->name && $confirmation !== 'DELETE') {
			$this->addFlash('error', sprintf('Confirmation did not match. Type "%s" or "DELETE" to confirm.', $language->name));
			return $this->redirectToRoute('admin_languages_index');
		}

		$this->entityManager->wrapInTransaction(function () use ($language): void {
			foreach ([FixedHoliday::class, FloatingHoliday::class, FixedHolidayError::class, FloatingHolidayError::class, CategoryTranslation::class] as $entityClass) {
				$this->entityManager->createQueryBuilder()
					->delete($entityClass, 'e')
					->where('e.language = :code')
					->setParameter('code', $language->code)
					->getQuery()
					->execute();
			}
			$this->entityManager->remove($language);
		});

		$this->addFlash('success', sprintf('Language "%s" deleted along with all its translations.', $language->code));

		return $this->redirectToRoute('admin_languages_index');
	}
}
