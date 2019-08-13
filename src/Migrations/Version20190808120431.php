<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190808120431 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE app_jobs CHANGE insurance_name insurance_name VARCHAR(255) DEFAULT NULL, CHANGE insurance_country insurance_country VARCHAR(255) DEFAULT NULL, CHANGE insurance_zip insurance_zip VARCHAR(255) DEFAULT NULL, CHANGE insurance_city insurance_city VARCHAR(255) DEFAULT NULL, CHANGE insurance_street insurance_street VARCHAR(255) DEFAULT NULL, CHANGE insurance_contact_name insurance_contact_name VARCHAR(255) DEFAULT NULL, CHANGE supplier_name supplier_name VARCHAR(255) DEFAULT NULL, CHANGE supplier_country supplier_country VARCHAR(255) DEFAULT NULL, CHANGE supplier_zip supplier_zip VARCHAR(255) DEFAULT NULL, CHANGE supplier_city supplier_city VARCHAR(255) DEFAULT NULL, CHANGE supplier_street supplier_street VARCHAR(255) DEFAULT NULL, CHANGE client_name client_name VARCHAR(255) DEFAULT NULL, CHANGE client_country client_country VARCHAR(255) DEFAULT NULL, CHANGE client_zip client_zip VARCHAR(15) DEFAULT NULL, CHANGE client_city client_city VARCHAR(255) DEFAULT NULL, CHANGE client_street client_street VARCHAR(255) DEFAULT NULL, CHANGE insurance_damage_no insurance_damage_no VARCHAR(50) DEFAULT NULL, CHANGE insurance_contract_no insurance_contract_no VARCHAR(50) DEFAULT NULL, CHANGE insurance_vu_nr insurance_vu_nr VARCHAR(10) DEFAULT NULL, CHANGE damage_job damage_job LONGTEXT DEFAULT NULL, CHANGE damage_name damage_name VARCHAR(255) DEFAULT NULL, CHANGE damage_street damage_street VARCHAR(255) DEFAULT NULL, CHANGE damage_zip damage_zip VARCHAR(255) DEFAULT NULL, CHANGE damage_city damage_city VARCHAR(255) DEFAULT NULL, CHANGE damage_country damage_country VARCHAR(255) DEFAULT NULL, CHANGE job_enter job_enter VARCHAR(255) DEFAULT NULL, CHANGE dl_no dl_no VARCHAR(255) DEFAULT NULL, CHANGE dlp_no dlp_no VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE app_jobs CHANGE insurance_name insurance_name VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE insurance_country insurance_country VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE insurance_zip insurance_zip VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE insurance_city insurance_city VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE insurance_street insurance_street VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE insurance_contact_name insurance_contact_name VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE supplier_name supplier_name VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE supplier_country supplier_country VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE supplier_zip supplier_zip VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE supplier_city supplier_city VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE supplier_street supplier_street VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE client_name client_name VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE client_country client_country VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE client_zip client_zip VARCHAR(15) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE client_city client_city VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE client_street client_street VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE insurance_damage_no insurance_damage_no VARCHAR(50) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE insurance_contract_no insurance_contract_no VARCHAR(50) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE insurance_vu_nr insurance_vu_nr VARCHAR(10) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE damage_job damage_job LONGTEXT NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE damage_name damage_name VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE damage_street damage_street VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE damage_zip damage_zip VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE damage_city damage_city VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE damage_country damage_country VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE job_enter job_enter VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE dl_no dl_no VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, CHANGE dlp_no dlp_no VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci');
    }
}
