<?php

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use App\Tests\Fixture\AdminUserFixture;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityControllerTest extends WebTestCase
{
	private const string PASSWORD = 'correct horse battery staple';

	private KernelBrowser $client;
	private AbstractDatabaseTool $databaseTool;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->client = static::createClient();
		$this->databaseTool = static::getContainer()
			->get(DatabaseToolCollection::class)
			->get();
		$fixtures = $this->databaseTool->loadFixtures([AdminUserFixture::class]);

		// The fixture carries a placeholder hash because every other test authenticates with
		// `loginUser`; this suite is the one that actually posts the form, so it needs a real one.
		$admin = $fixtures->getReferenceRepository()->getReference(AdminUserFixture::ADMIN, AdminUser::class);
		$hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
		$admin->setPassword($hasher->hashPassword($admin, self::PASSWORD));
		static::getContainer()->get(EntityManagerInterface::class)->flush();
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
	}

	/**
	 * Password managers match fields by name and `autocomplete` token, so these names are part of
	 * the page's contract and must stay in sync with `username_parameter` / `password_parameter`.
	 */
	public function testLoginFormExposesPasswordManagerFriendlyFields(): void
	{
		$crawler = $this->client->request('GET', '/');

		$this->assertResponseIsSuccessful();
		$this->assertSame(1, $crawler->filter('input[name="username"][autocomplete="username"]')->count());
		$this->assertSame(1, $crawler->filter('input[name="password"][autocomplete="current-password"]')->count());
		$this->assertSame(1, $crawler->filter('form[action="/"][method="post"]')->count());
	}

	public function testLoginWithEmailSucceeds(): void
	{
		$this->submitLogin('admin@null.com', self::PASSWORD);

		$this->assertResponseRedirects('/admin/');
	}

	public function testLoginWithUsernameSucceeds(): void
	{
		$this->submitLogin('admin', self::PASSWORD);

		$this->assertResponseRedirects('/admin/');
	}

	public function testLoginWithWrongPasswordFails(): void
	{
		$this->submitLogin('admin', 'nope');

		$this->assertResponseRedirects('http://localhost/');
		$crawler = $this->client->followRedirect();
		$this->assertSame(1, $crawler->filter('.login-error')->count());
	}

	public function testAuthenticatedRootRedirectsToAdmin(): void
	{
		$this->submitLogin('admin', self::PASSWORD);
		$this->client->request('GET', '/');

		$this->assertResponseRedirects();
	}

	public function testLogoutClearsTheSession(): void
	{
		$this->submitLogin('admin', self::PASSWORD);
		$this->client->request('GET', '/admin/');
		$this->assertResponseIsSuccessful();

		$crawler = $this->client->request('GET', '/admin/');
		$logout = $crawler->filter('.topbar-account-logout')->attr('href');
		$this->client->request('GET', $logout);
		$this->assertResponseRedirects('http://localhost/');

		$this->client->request('GET', '/admin/');
		$this->assertResponseRedirects('http://localhost/');
	}

	private function submitLogin(string $identifier, string $password): void
	{
		$crawler = $this->client->request('GET', '/');
		$form = $crawler->selectButton('Sign in')->form();
		$this->client->submit($form, ['username' => $identifier, 'password' => $password]);
	}
}
