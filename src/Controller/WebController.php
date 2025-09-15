<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WebController extends AbstractController
{
	#[Route('/', name: 'index')]
	public function index(): Response
	{
		return $this->render('index.html.twig');
	}
}
