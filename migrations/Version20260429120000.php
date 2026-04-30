<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260429120000 extends AbstractMigration
{
	#[Override]
	public function getDescription(): string
	{
		return 'Convert holiday-category relation to many-to-many; add category translations';
	}

	#[Override]
	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE category CHANGE COLUMN name slug VARCHAR(63) NOT NULL');

		$this->addSql('CREATE TABLE category_translation (
			category_id INT NOT NULL,
			language_code VARCHAR(31) NOT NULL,
			name VARCHAR(127) NOT NULL,
			INDEX IDX_CT_CATEGORY (category_id),
			INDEX IDX_CT_LANGUAGE (language_code),
			PRIMARY KEY(category_id, language_code)
		) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

		$this->addSql('ALTER TABLE category_translation
			ADD CONSTRAINT FK_CT_CATEGORY FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE,
			ADD CONSTRAINT FK_CT_LANGUAGE FOREIGN KEY (language_code) REFERENCES language (code) ON DELETE CASCADE');

		$this->addSql('CREATE TABLE fixed_holiday_metadata_category (
			metadata_id INT NOT NULL,
			category_id INT NOT NULL,
			INDEX IDX_FHMC_METADATA (metadata_id),
			INDEX IDX_FHMC_CATEGORY (category_id),
			PRIMARY KEY(metadata_id, category_id)
		) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

		$this->addSql('CREATE TABLE floating_holiday_metadata_category (
			metadata_id INT NOT NULL,
			category_id INT NOT NULL,
			INDEX IDX_FLHMC_METADATA (metadata_id),
			INDEX IDX_FLHMC_CATEGORY (category_id),
			PRIMARY KEY(metadata_id, category_id)
		) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

		$this->addSql('ALTER TABLE fixed_holiday_metadata_category
			ADD CONSTRAINT FK_FHMC_METADATA FOREIGN KEY (metadata_id) REFERENCES fixed_holiday_metadata (id) ON DELETE CASCADE,
			ADD CONSTRAINT FK_FHMC_CATEGORY FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');

		$this->addSql('ALTER TABLE floating_holiday_metadata_category
			ADD CONSTRAINT FK_FLHMC_METADATA FOREIGN KEY (metadata_id) REFERENCES floating_holiday_metadata (id) ON DELETE CASCADE,
			ADD CONSTRAINT FK_FLHMC_CATEGORY FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');

		$this->addSql('INSERT INTO fixed_holiday_metadata_category (metadata_id, category_id)
			SELECT id, category_id FROM fixed_holiday_metadata WHERE category_id IS NOT NULL');

		$this->addSql('INSERT INTO floating_holiday_metadata_category (metadata_id, category_id)
			SELECT id, category_id FROM floating_holiday_metadata WHERE category_id IS NOT NULL');

		$this->dropCategoryFk($schema, 'fixed_holiday_metadata');
		$this->dropCategoryFk($schema, 'floating_holiday_metadata');

		$this->addSql('ALTER TABLE fixed_holiday_metadata DROP COLUMN category_id');
		$this->addSql('ALTER TABLE floating_holiday_metadata DROP COLUMN category_id');
	}

	#[Override]
	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE fixed_holiday_metadata ADD category_id INT DEFAULT NULL');
		$this->addSql('ALTER TABLE floating_holiday_metadata ADD category_id INT DEFAULT NULL');

		$this->addSql('ALTER TABLE fixed_holiday_metadata
			ADD CONSTRAINT FK_FHM_CATEGORY FOREIGN KEY (category_id) REFERENCES category (id)');
		$this->addSql('ALTER TABLE floating_holiday_metadata
			ADD CONSTRAINT FK_FLHM_CATEGORY FOREIGN KEY (category_id) REFERENCES category (id)');

		$this->addSql('UPDATE fixed_holiday_metadata m SET category_id = (
			SELECT category_id FROM fixed_holiday_metadata_category WHERE metadata_id = m.id LIMIT 1
		)');
		$this->addSql('UPDATE floating_holiday_metadata m SET category_id = (
			SELECT category_id FROM floating_holiday_metadata_category WHERE metadata_id = m.id LIMIT 1
		)');

		$this->addSql('DROP TABLE fixed_holiday_metadata_category');
		$this->addSql('DROP TABLE floating_holiday_metadata_category');

		$this->addSql('DROP TABLE category_translation');
		$this->addSql('ALTER TABLE category CHANGE COLUMN slug name VARCHAR(63) NOT NULL');
	}

	private function dropCategoryFk(Schema $schema, string $tableName): void
	{
		if (!$schema->hasTable($tableName)) {
			return;
		}
		$table = $schema->getTable($tableName);
		foreach ($table->getForeignKeys() as $fk) {
			if (in_array('category_id', $fk->getLocalColumns(), true)) {
				$this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $tableName, $fk->getName()));
				return;
			}
		}
	}
}
