<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190809062906 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE message_type (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, gdv INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message_type_job (message_type_id INT NOT NULL, job_id INT NOT NULL, INDEX IDX_F0E9C20855C4B69F (message_type_id), INDEX IDX_F0E9C208BE04EA9 (job_id), PRIMARY KEY(message_type_id, job_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE message_type_job ADD CONSTRAINT FK_F0E9C20855C4B69F FOREIGN KEY (message_type_id) REFERENCES message_type (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_type_job ADD CONSTRAINT FK_F0E9C208BE04EA9 FOREIGN KEY (job_id) REFERENCES app_jobs (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE message_type_job DROP FOREIGN KEY FK_F0E9C20855C4B69F');
        $this->addSql('DROP TABLE message_type');
        $this->addSql('DROP TABLE message_type_job');
    }
}
