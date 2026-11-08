<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261108120001 extends AbstractMigration
{
	#[Override]
	public function getDescription(): string
	{
		return 'Create holiday_blacklist, the holiday names rejected on the admin import page';
	}

	#[Override]
	public function up(Schema $schema): void
	{
		$this->addSql('CREATE TABLE holiday_blacklist (
			id INT AUTO_INCREMENT NOT NULL,
			name VARCHAR(255) NOT NULL,
			dates JSON NOT NULL,
			reason VARCHAR(16) NOT NULL,
			comment VARCHAR(1023) DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY(id)
		) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
	}

	#[Override]
	public function down(Schema $schema): void
	{
		$this->addSql('DROP TABLE holiday_blacklist');
	}
}
