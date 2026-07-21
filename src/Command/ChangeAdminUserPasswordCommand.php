<?php

namespace App\Command;

use App\Repository\AdminUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * There is no password reset flow in the panel (no mailer), so this command is how a forgotten or
 * compromised admin password gets replaced.
 */
#[AsCommand(name: 'app:user:password', description: 'Changes an admin account password')]
class ChangeAdminUserPasswordCommand extends Command
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly AdminUserRepository $adminUsers,
		private readonly UserPasswordHasherInterface $passwordHasher
	) {
		parent::__construct();
	}

	#[Override]
	protected function configure(): void
	{
		$this->addArgument('user', InputArgument::OPTIONAL, 'E-mail or username of the account');
	}

	#[Override]
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$identifier = $input->getArgument('user');
		if ($identifier === null) {
			$identifier = $io->ask('E-mail or username');
		}
		$identifier = trim((string)$identifier);

		$user = $this->adminUsers->findOneByIdentifier($identifier);
		if ($user === null) {
			$io->error(sprintf('No admin account matches "%s".', $identifier));
			return Command::FAILURE;
		}

		$question = new Question('New password (leave empty to generate one)');
		$question->setHidden(true);
		$question->setHiddenFallback(false);
		$plainPassword = trim((string)$io->askQuestion($question));
		$generated = $plainPassword === '';
		if ($generated) {
			$plainPassword = PasswordGenerator::generate();
		}

		$user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
		$this->entityManager->flush();

		$io->success(sprintf('Password changed for "%s" <%s>.', $user->username, $user->email));
		if ($generated) {
			$io->writeln(sprintf('Generated password: <info>%s</info>', $plainPassword));
			$io->warning('This password is shown once and is not recoverable.');
		}

		// Sessions are server-side and not tied to the hash, so an already signed-in browser keeps
		// its session until it expires. Nothing here revokes it.
		$io->note('Existing sessions are not revoked; only new logins need the new password.');
		return Command::SUCCESS;
	}
}
