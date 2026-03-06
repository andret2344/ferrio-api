<?php

namespace App\Security;

use Override;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class FirebaseUser implements UserInterface
{
	public function __construct(private string $uid)
	{
	}

	#[Override]
	public function getRoles(): array
	{
		return ['ROLE_FIREBASE_USER'];
	}

	#[Override]
	public function eraseCredentials(): void
	{
		// empty
	}

	#[Override]
	public function getUserIdentifier(): string
	{
		return $this->uid;
	}
}
