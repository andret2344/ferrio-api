<?php

namespace App\Entity;

use App\Repository\AdminUserRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * An admin panel account. Distinct from the Firebase end-users, who have no DB table at all and exist
 * only as a UID repeated across the report tables.
 */
#[ORM\Entity(repositoryClass: AdminUserRepository::class)]
#[ORM\Table(name: 'admin_user')]
class AdminUser implements UserInterface, PasswordAuthenticatedUserInterface
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer')]
	private(set) int $id;

	#[ORM\Column(length: 180, unique: true)]
	private(set) string $email;

	#[ORM\Column(length: 180, unique: true)]
	private(set) string $username;

	#[ORM\Column]
	private(set) string $password;

	/** @var string[] */
	#[ORM\Column(type: 'json')]
	private(set) array $roles;

	#[ORM\Column(type: 'datetimetz_immutable')]
	private(set) DateTimeImmutable $createdAt;

	/**
	 * Deterministic Gravatar identicon keyed by the account's e-mail, matching the avatar rule
	 * `FirebaseUserLookup` uses for reporters.
	 */
	public string $avatarUrl {
		get => sprintf('https://gravatar.com/avatar/%s?d=identicon', hash('sha256', strtolower($this->email)));
	}

	/**
	 * @param string[] $roles
	 */
	public function __construct(
		string $email,
		string $username,
		string $password,
		array $roles = ['ROLE_ADMIN'],
		?DateTimeImmutable $createdAt = null
	) {
		$this->email = $email;
		$this->username = $username;
		$this->password = $password;
		$this->roles = $roles;
		if ($createdAt === null) {
			$this->createdAt = new DateTimeImmutable();
		} else {
			$this->createdAt = $createdAt;
		}
	}

	public function setPassword(string $password): void
	{
		$this->password = $password;
	}

	#[Override]
	public function getUserIdentifier(): string
	{
		return $this->email;
	}

	#[Override]
	public function getPassword(): string
	{
		return $this->password;
	}

	/**
	 * @return string[]
	 */
	#[Override]
	public function getRoles(): array
	{
		$roles = $this->roles;
		$roles[] = 'ROLE_USER';
		return array_values(array_unique($roles));
	}
}
