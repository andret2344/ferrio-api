<?php

namespace App\Controller;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class SecurityController extends AbstractController
{
	/**
	 * Never executed: the firewall intercepts this path and performs the logout itself. The route
	 * exists only so `path('admin_logout')` resolves.
	 */
	#[Route('/admin/logout', name: 'admin_logout')]
	public function logout(): never
	{
		throw new LogicException('This method is intercepted by the logout key on the firewall.');
	}
}
