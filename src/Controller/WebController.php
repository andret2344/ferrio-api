<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class WebController extends AbstractController
{
	/**
	 * The app root is the login gate: it is both `login_path` and `check_path`, so the firewall
	 * intercepts the POST before this ever runs and only the GET reaches here.
	 */
	#[Route('/', name: 'index')]
	public function index(AuthenticationUtils $authenticationUtils): Response
	{
		if ($this->getUser() !== null) {
			return $this->redirectToRoute('admin_index');
		}
		return $this->render('index.html.twig', [
			'error' => $authenticationUtils->getLastAuthenticationError(),
			'lastUsername' => $authenticationUtils->getLastUsername()
		]);
	}
}
