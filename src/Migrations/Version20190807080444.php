<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190807080444 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE app_actions (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, gdv INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_areas (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, gdv INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_contracts (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, gdv INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_damages (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, gdv INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE file (id INT AUTO_INCREMENT NOT NULL, job_id INT NOT NULL, path VARCHAR(255) NOT NULL, filename VARCHAR(255) NOT NULL, filetype VARCHAR(255) NOT NULL, upload_date DATETIME NOT NULL, report_type VARCHAR(255) NOT NULL, INDEX IDX_8C9F3610BE04EA9 (job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE insurance (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, dl_no VARCHAR(20) NOT NULL, dlp_no VARCHAR(20) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_jobs (id INT AUTO_INCREMENT NOT NULL, damage_id INT DEFAULT NULL, area_id INT DEFAULT NULL, contract_id INT DEFAULT NULL, insurance_name VARCHAR(255) NOT NULL, insurance_country VARCHAR(255) NOT NULL, insurance_zip VARCHAR(255) NOT NULL, insurance_city VARCHAR(255) NOT NULL, insurance_street VARCHAR(255) NOT NULL, insurance_contact_name VARCHAR(255) NOT NULL, insurance_contact_telephone VARCHAR(255) DEFAULT NULL, insurance_contact_fax VARCHAR(255) DEFAULT NULL, insurance_contact_comment VARCHAR(255) DEFAULT NULL, supplier_name VARCHAR(255) NOT NULL, supplier_country VARCHAR(255) NOT NULL, supplier_telephone VARCHAR(255) DEFAULT NULL, supplier_fax VARCHAR(255) DEFAULT NULL, supplier_zip VARCHAR(255) NOT NULL, supplier_city VARCHAR(255) NOT NULL, supplier_street VARCHAR(255) NOT NULL, client_name VARCHAR(255) NOT NULL, client_country VARCHAR(255) NOT NULL, client_zip VARCHAR(15) NOT NULL, client_city VARCHAR(255) NOT NULL, client_street VARCHAR(255) NOT NULL, client_mobile VARCHAR(255) DEFAULT NULL, client_telephone VARCHAR(255) DEFAULT NULL, client_fax VARCHAR(255) DEFAULT NULL, insurance_damage_no VARCHAR(50) NOT NULL, insurance_damage_date DATETIME NOT NULL, insurance_damage_date_report DATETIME NOT NULL, insurance_contract_no VARCHAR(50) NOT NULL, insurance_vu_nr VARCHAR(10) NOT NULL, damage_description LONGTEXT DEFAULT NULL, damage_job LONGTEXT NOT NULL, reference_no VARCHAR(255) NOT NULL, create_date_time DATETIME NOT NULL, damage_name VARCHAR(255) NOT NULL, damage_street VARCHAR(255) NOT NULL, damage_zip VARCHAR(255) NOT NULL, damage_city VARCHAR(255) NOT NULL, damage_country VARCHAR(255) NOT NULL, receipt TINYINT(1) NOT NULL, email_sent TINYINT(1) NOT NULL, receipt_date DATETIME NOT NULL, receipt_status INT NOT NULL, receipt_message VARCHAR(255) DEFAULT NULL, finish_date DATETIME DEFAULT NULL, job_enter VARCHAR(255) NOT NULL, dl_no VARCHAR(255) NOT NULL, dlp_no VARCHAR(255) NOT NULL, crypt LONGTEXT NOT NULL, INDEX IDX_ADBD48656CE425B7 (damage_id), INDEX IDX_ADBD4865BD0F409C (area_id), INDEX IDX_ADBD48652576E0FD (contract_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE job_action (job_id INT NOT NULL, action_id INT NOT NULL, INDEX IDX_17C8A113BE04EA9 (job_id), INDEX IDX_17C8A1139D32F035 (action_id), PRIMARY KEY(job_id, action_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE result (id INT AUTO_INCREMENT NOT NULL, job_id INT DEFAULT NULL, text VARCHAR(255) NOT NULL, email_sent TINYINT(1) NOT NULL, INDEX IDX_136AC113BE04EA9 (job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_C2502824F85E0677 (username), UNIQUE INDEX UNIQ_C2502824E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F3610BE04EA9 FOREIGN KEY (job_id) REFERENCES app_jobs (id)');
        $this->addSql('ALTER TABLE app_jobs ADD CONSTRAINT FK_ADBD48656CE425B7 FOREIGN KEY (damage_id) REFERENCES app_damages (id)');
        $this->addSql('ALTER TABLE app_jobs ADD CONSTRAINT FK_ADBD4865BD0F409C FOREIGN KEY (area_id) REFERENCES app_areas (id)');
        $this->addSql('ALTER TABLE app_jobs ADD CONSTRAINT FK_ADBD48652576E0FD FOREIGN KEY (contract_id) REFERENCES app_contracts (id)');
        $this->addSql('ALTER TABLE job_action ADD CONSTRAINT FK_17C8A113BE04EA9 FOREIGN KEY (job_id) REFERENCES app_jobs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_action ADD CONSTRAINT FK_17C8A1139D32F035 FOREIGN KEY (action_id) REFERENCES app_actions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE result ADD CONSTRAINT FK_136AC113BE04EA9 FOREIGN KEY (job_id) REFERENCES app_jobs (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE job_action DROP FOREIGN KEY FK_17C8A1139D32F035');
        $this->addSql('ALTER TABLE app_jobs DROP FOREIGN KEY FK_ADBD4865BD0F409C');
        $this->addSql('ALTER TABLE app_jobs DROP FOREIGN KEY FK_ADBD48652576E0FD');
        $this->addSql('ALTER TABLE app_jobs DROP FOREIGN KEY FK_ADBD48656CE425B7');
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F3610BE04EA9');
        $this->addSql('ALTER TABLE job_action DROP FOREIGN KEY FK_17C8A113BE04EA9');
        $this->addSql('ALTER TABLE result DROP FOREIGN KEY FK_136AC113BE04EA9');
        $this->addSql('DROP TABLE app_actions');
        $this->addSql('DROP TABLE app_areas');
        $this->addSql('DROP TABLE app_contracts');
        $this->addSql('DROP TABLE app_damages');
        $this->addSql('DROP TABLE file');
        $this->addSql('DROP TABLE insurance');
        $this->addSql('DROP TABLE app_jobs');
        $this->addSql('DROP TABLE job_action');
        $this->addSql('DROP TABLE result');
        $this->addSql('DROP TABLE app_users');
    }
}
