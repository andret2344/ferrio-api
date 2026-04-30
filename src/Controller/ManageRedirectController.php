<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ManageRedirectController extends AbstractController
{
	#[Route('/manage', name: 'manage_legacy_redirect_root', priority: -1)]
	#[Route('/manage/{path}', name: 'manage_legacy_redirect', requirements: ['path' => '.*'], priority: -1)]
	public function redirectLegacy(Request $request, string $path = ''): RedirectResponse
	{
		$query = $request->getQueryString();
		$target = '/admin' . ($path === '' ? '' : '/' . $path) . ($query !== null ? '?' . $query : '');
		return $this->redirect($target, 301);
	}
}
