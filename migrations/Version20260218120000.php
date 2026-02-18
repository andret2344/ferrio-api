<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260218120000 extends AbstractMigration
{
	#[Override]
	public function getDescription(): string
	{
		return 'Add algorithm and algorithm_args columns to floating_holiday_metadata';
	}

	#[Override]
	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE floating_holiday_metadata ADD algorithm VARCHAR(255) NOT NULL');
		$this->addSql('ALTER TABLE floating_holiday_metadata ADD algorithm_args VARCHAR(255) DEFAULT NULL');
	}

	#[Override]
	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE floating_holiday_metadata DROP COLUMN algorithm');
		$this->addSql('ALTER TABLE floating_holiday_metadata DROP COLUMN algorithm_args');
	}
}
