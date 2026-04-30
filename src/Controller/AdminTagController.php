<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\CategoryTranslation;
use App\Entity\Language;
use App\Repository\CategoryRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tags', name: 'admin_tags_')]
class AdminTagController extends AbstractController
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly CategoryRepository     $categoryRepository,
	)
	{
	}

	#[Route('', name: 'index', methods: ['GET'])]
	public function index(): Response
	{
		$languages = $this->entityManager->getRepository(Language::class)
			->findBy([], ['code' => 'ASC']);

		return $this->render('admin/tags/index.html.twig', [
			'tags' => $this->categoryRepository->findAllWithCounts(),
			'languages' => $languages,
		]);
	}

	#[Route('', name: 'create', methods: ['POST'])]
	public function create(Request $request): RedirectResponse
	{
		$slug = trim((string)$request->request->get('slug', ''));
		if ($slug === '') {
			$this->addFlash('error', 'Slug cannot be empty.');
			return $this->redirectToRoute('admin_tags_index');
		}
		if (mb_strlen($slug) > 63) {
			$this->addFlash('error', 'Slug must be at most 63 characters.');
			return $this->redirectToRoute('admin_tags_index');
		}

		$this->entityManager->persist(new Category($slug));
		try {
			$this->entityManager->flush();
			$this->addFlash('success', sprintf('Tag "%s" created.', $slug));
		} catch (UniqueConstraintViolationException) {
			$this->addFlash('error', sprintf('Tag "%s" already exists.', $slug));
		}

		return $this->redirectToRoute('admin_tags_index');
	}

	#[Route('/{id<\d+>}/translations', name: 'translations_save', methods: ['POST'])]
	public function saveTranslations(int $id, Request $request): RedirectResponse
	{
		$category = $this->categoryRepository->find($id);
		if ($category === null) {
			$this->addFlash('error', 'Tag not found.');
			return $this->redirectToRoute('admin_tags_index');
		}

		/** @var array<string, string> $names */
		$names = (array)$request->request->all('names');

		$existing = [];
		foreach ($category->translations as $translation) {
			$existing[$translation->language->code] = $translation;
		}

		foreach ($names as $code => $name) {
			$name = trim((string)$name);
			$translation = $existing[$code] ?? null;

			if ($name === '') {
				if ($translation !== null) {
					$category->translations->removeElement($translation);
					$this->entityManager->remove($translation);
				}
				continue;
			}

			if ($translation !== null) {
				$translation->name = $name;
			} else {
				$language = $this->entityManager->getRepository(Language::class)->find($code);
				if ($language === null) {
					continue;
				}
				$category->translations->add(new CategoryTranslation($category, $language, $name));
			}
		}

		$this->entityManager->flush();
		$this->addFlash('success', sprintf('Translations for "%s" saved.', $category->slug));

		return $this->redirectToRoute('admin_tags_index');
	}

	#[Route('/{id<\d+>}/delete', name: 'delete', methods: ['POST'])]
	public function delete(int $id): RedirectResponse
	{
		$category = $this->categoryRepository->find($id);
		if ($category === null) {
			$this->addFlash('error', 'Tag not found.');
			return $this->redirectToRoute('admin_tags_index');
		}

		$slug = $category->slug;
		$this->entityManager->remove($category);
		$this->entityManager->flush();
		$this->addFlash('success', sprintf('Tag "%s" deleted.', $slug));

		return $this->redirectToRoute('admin_tags_index');
	}
}
