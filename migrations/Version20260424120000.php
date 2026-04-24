<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260424120000 extends AbstractMigration
{
	#[Override]
	public function getDescription(): string
	{
		return 'Add comment column to report tables (fixed/floating suggestions and errors)';
	}

	#[Override]
	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE fixed_holiday_suggestion ADD comment LONGTEXT DEFAULT NULL');
		$this->addSql('ALTER TABLE floating_holiday_suggestion ADD comment LONGTEXT DEFAULT NULL');
		$this->addSql('ALTER TABLE fixed_holiday_error ADD comment LONGTEXT DEFAULT NULL');
		$this->addSql('ALTER TABLE floating_holiday_error ADD comment LONGTEXT DEFAULT NULL');
	}

	#[Override]
	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE fixed_holiday_suggestion DROP COLUMN comment');
		$this->addSql('ALTER TABLE floating_holiday_suggestion DROP COLUMN comment');
		$this->addSql('ALTER TABLE fixed_holiday_error DROP COLUMN comment');
		$this->addSql('ALTER TABLE floating_holiday_error DROP COLUMN comment');
	}
}
