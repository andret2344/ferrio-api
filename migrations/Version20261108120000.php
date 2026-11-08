<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261108120000 extends AbstractMigration
{
	#[Override]
	public function getDescription(): string
	{
		return 'Create holiday_import_candidate, the tiles of the admin import page that outlive a single paste';
	}

	#[Override]
	public function up(Schema $schema): void
	{
		$this->addSql('CREATE TABLE holiday_import_candidate (
			id INT AUTO_INCREMENT NOT NULL,
			name VARCHAR(255) NOT NULL,
			dates JSON NOT NULL,
			ignored_date_pairs JSON NOT NULL,
			linked_kind VARCHAR(8) DEFAULT NULL,
			linked_metadata_id INT DEFAULT NULL,
			verification JSON DEFAULT NULL,
			verified_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY(id)
		) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
	}

	#[Override]
	public function down(Schema $schema): void
	{
		$this->addSql('DROP TABLE holiday_import_candidate');
	}
}
