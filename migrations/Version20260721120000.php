<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260721120000 extends AbstractMigration
{
	#[Override]
	public function getDescription(): string
	{
		return 'Create admin_user and seed the first admin from the retired HTTP_BASIC_AUTH_* env vars, replacing the in-memory Basic Auth user with a real form-login account';
	}

	#[Override]
	public function up(Schema $schema): void
	{
		$this->addSql('CREATE TABLE admin_user (
			id INT AUTO_INCREMENT NOT NULL,
			email VARCHAR(180) NOT NULL,
			username VARCHAR(180) NOT NULL,
			password VARCHAR(255) NOT NULL,
			roles JSON NOT NULL,
			created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetimetz_immutable)\',
			UNIQUE INDEX UNIQ_ADMIN_USER_EMAIL (email),
			UNIQUE INDEX UNIQ_ADMIN_USER_USERNAME (username),
			PRIMARY KEY(id)
		) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

		$username = getenv('HTTP_BASIC_AUTH_USERNAME');
		$password = getenv('HTTP_BASIC_AUTH_PASSWORD');
		if ($username === false || $password === false || $username === '' || $password === '') {
			// Nothing to carry over: the first admin is created with `php bin/console app:user:create`.
			return;
		}

		// Symfony's `auto` hasher verifies bcrypt regardless of which code produced it, so hashing
		// here rather than through the container keeps the migration free of service dependencies.
		$this->addSql(
			'INSERT INTO admin_user (email, username, password, roles, created_at) VALUES (?, ?, ?, ?, NOW())',
			[
				'admin@null.com',
				$username,
				password_hash($password, PASSWORD_BCRYPT),
				json_encode(['ROLE_ADMIN'])
			]
		);
	}

	#[Override]
	public function down(Schema $schema): void
	{
		$this->addSql('DROP TABLE admin_user');
	}
}
