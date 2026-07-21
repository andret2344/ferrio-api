<?php

namespace App\Tests\Command;

use App\Repository\AdminUserRepository;
use App\Tests\Fixture\AdminUserFixture;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangeAdminUserPasswordCommandTest extends KernelTestCase
{
	private AbstractDatabaseTool $databaseTool;
	private CommandTester $tester;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		self::bootKernel();
		$this->databaseTool = static::getContainer()
			->get(DatabaseToolCollection::class)
			->get();
		$this->databaseTool->loadFixtures([AdminUserFixture::class]);

		$application = new Application(static::$kernel);
		$this->tester = new CommandTester($application->find('app:user:password'));
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
	}

	public function testChangesPasswordToTheGivenOne(): void
	{
		$this->tester->setInputs(['s3cret-passphrase']);
		$this->tester->execute(['user' => 'admin']);

		$this->tester->assertCommandIsSuccessful();
		$this->assertTrue($this->passwordMatches('s3cret-passphrase'));
	}

	public function testAcceptsTheEmailAsIdentifier(): void
	{
		$this->tester->setInputs(['another-passphrase']);
		$this->tester->execute(['user' => 'admin@null.com']);

		$this->tester->assertCommandIsSuccessful();
		$this->assertTrue($this->passwordMatches('another-passphrase'));
	}

	public function testGeneratesAndPrintsAPasswordWhenLeftEmpty(): void
	{
		$this->tester->setInputs(['']);
		$this->tester->execute(['user' => 'admin']);

		$this->tester->assertCommandIsSuccessful();

		$output = $this->tester->getDisplay();
		$this->assertMatchesRegularExpression('/Generated password: (\S+)/', $output);
		preg_match('/Generated password: (\S+)/', $output, $matches);
		$this->assertTrue($this->passwordMatches($matches[1]));
	}

	public function testFailsOnUnknownUser(): void
	{
		$this->tester->execute(['user' => 'nobody']);

		$this->assertSame(1, $this->tester->getStatusCode());
		$this->assertStringContainsString('No admin account matches', $this->tester->getDisplay());
	}

	private function passwordMatches(string $plainPassword): bool
	{
		$repository = static::getContainer()->get(AdminUserRepository::class);
		$hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
		return $hasher->isPasswordValid($repository->findOneByIdentifier('admin'), $plainPassword);
	}
}
