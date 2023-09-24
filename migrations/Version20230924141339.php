<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230924141339 extends AbstractMigration {
	public function getDescription(): string {
		return 'Rename to "fixed"';
	}

	public function up(Schema $schema): void {
		$this->addSql('RENAME TABLE holiday TO fixed_holiday');
		$this->addSql('RENAME TABLE holiday_metadata TO fixed_holiday_metadata');
	}

	public function down(Schema $schema): void {
		$this->addSql('RENAME TABLE fixed_holiday TO holiday');
		$this->addSql('RENAME TABLE fixed_holiday_metadata TO holiday_metadata');
	}
}
