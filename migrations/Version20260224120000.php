<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260224120000 extends AbstractMigration
{
	#[Override]
	public function getDescription(): string
	{
		return 'Create poll, poll_option, and poll_vote tables';
	}

	#[Override]
	public function up(Schema $schema): void
	{
		$this->addSql('CREATE TABLE poll (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            question LONGTEXT NOT NULL,
            start DATETIME NOT NULL COMMENT \'(DC2Type:datetimetz_immutable)\',
            end DATETIME NOT NULL COMMENT \'(DC2Type:datetimetz_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

		$this->addSql('CREATE TABLE poll_option (
            id INT AUTO_INCREMENT NOT NULL,
            poll_id INT NOT NULL,
            text VARCHAR(255) NOT NULL,
            INDEX IDX_poll_option_poll_id (poll_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

		$this->addSql('CREATE TABLE poll_vote (
            id INT AUTO_INCREMENT NOT NULL,
            option_id INT NOT NULL,
            poll_id INT NOT NULL,
            user_id VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetimetz_immutable)\',
            INDEX IDX_poll_vote_option_id (option_id),
            INDEX IDX_poll_vote_poll_id (poll_id),
            UNIQUE INDEX UNIQ_poll_vote_user_poll (user_id, poll_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

		$this->addSql('ALTER TABLE poll_option ADD CONSTRAINT FK_poll_option_poll_id FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE');
		$this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_poll_vote_option_id FOREIGN KEY (option_id) REFERENCES poll_option (id) ON DELETE CASCADE');
		$this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_poll_vote_poll_id FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE');
	}

	#[Override]
	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE poll_vote DROP FOREIGN KEY FK_poll_vote_option_id');
		$this->addSql('ALTER TABLE poll_vote DROP FOREIGN KEY FK_poll_vote_poll_id');
		$this->addSql('ALTER TABLE poll_option DROP FOREIGN KEY FK_poll_option_poll_id');
		$this->addSql('DROP TABLE poll_vote');
		$this->addSql('DROP TABLE poll_option');
		$this->addSql('DROP TABLE poll');
	}
}
