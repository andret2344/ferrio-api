<?php

namespace App\Command;

use App\Entity\AdminUser;
use App\Repository\AdminUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The only way to provision an admin: there is no in-panel admin management UI and no password
 * reset, so a forgotten password is fixed by re-running this command.
 */
#[AsCommand(name: 'app:user:create', description: 'Creates an admin panel account')]
class CreateAdminUserCommand extends Command
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly AdminUserRepository $adminUsers,
		private readonly UserPasswordHasherInterface $passwordHasher
	) {
		parent::__construct();
	}

	#[Override]
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$email = $io->ask('E-mail', null, function (?string $value): string {
			$value = trim((string)$value);
			if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
				throw new RuntimeException('Not a well-formed e-mail address.');
			}
			return $value;
		});
		if ($this->adminUsers->findOneByIdentifier($email) !== null) {
			$io->error(sprintf('An account with the e-mail "%s" already exists.', $email));
			return Command::FAILURE;
		}

		$username = $io->ask('Username', null, function (?string $value): string {
			$value = trim((string)$value);
			if ($value === '') {
				throw new RuntimeException('The username cannot be empty.');
			}
			return $value;
		});
		if ($this->adminUsers->findOneByIdentifier($username) !== null) {
			$io->error(sprintf('An account with the username "%s" already exists.', $username));
			return Command::FAILURE;
		}

		$question = new Question('Password (leave empty to generate one)');
		$question->setHidden(true);
		$question->setHiddenFallback(false);
		$plainPassword = trim((string)$io->askQuestion($question));
		$generated = $plainPassword === '';
		if ($generated) {
			$plainPassword = PasswordGenerator::generate();
		}

		// Re-checked after the prompts: they are interactive and slow enough for another run of this
		// same command to have taken the identifier in the meantime.
		if ($this->adminUsers->findOneByIdentifier($email) !== null
			|| $this->adminUsers->findOneByIdentifier($username) !== null) {
			$io->error('The e-mail or username was taken while this command was running.');
			return Command::FAILURE;
		}

		$user = new AdminUser($email, $username, '');
		$user->setPassword($this->hash($user, $plainPassword));
		$this->entityManager->persist($user);
		$this->entityManager->flush();

		$io->success(sprintf('Created admin "%s" <%s>.', $username, $email));
		if ($generated) {
			$io->writeln(sprintf('Generated password: <info>%s</info>', $plainPassword));
			$io->warning('This password is shown once and is not recoverable.');
		}
		return Command::SUCCESS;
	}

	private function hash(PasswordAuthenticatedUserInterface $user, string $plainPassword): string
	{
		return $this->passwordHasher->hashPassword($user, $plainPassword);
	}
}
