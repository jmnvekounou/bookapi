<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210220185353 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, phone_number VARCHAR(255) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, email VARCHAR(255) NOT NULL, details LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE closed_contract_info (id INT AUTO_INCREMENT NOT NULL, closed_at DATETIME NOT NULL, closed_by VARCHAR(255) NOT NULL, reason VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contract (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(255) NOT NULL, order_number VARCHAR(255) DEFAULT NULL, contract_number VARCHAR(255) NOT NULL, contractdate DATE DEFAULT NULL, methodofsupply VARCHAR(255) DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, address LONGTEXT DEFAULT NULL, startdate DATE NOT NULL, enddate DATE NOT NULL, hourly_rate INT NOT NULL, hour_number INT NOT NULL, estimated_cost DOUBLE PRECISION NOT NULL, total_hour DOUBLE PRECISION NOT NULL, total_cost DOUBLE PRECISION NOT NULL, initial_hour DOUBLE PRECISION NOT NULL, used_hour_date DATE DEFAULT NULL, time_type VARCHAR(255) DEFAULT NULL, initial_end_date DATE NOT NULL, received_at DATETIME NOT NULL, status SMALLINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, confirmation_sent TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contract_type (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(255) NOT NULL, designation VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, details LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE employee (id INT AUTO_INCREMENT NOT NULL, sexe VARCHAR(20) NOT NULL, name VARCHAR(255) NOT NULL, firstname VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, second_email VARCHAR(255) NOT NULL, home_phone_number VARCHAR(255) NOT NULL, mobile_number VARCHAR(255) NOT NULL, home_address VARCHAR(255) DEFAULT NULL, birthday_date DATE NOT NULL, hiring_date DATE DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE employee_profile (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, rubric VARCHAR(255) NOT NULL, created_by VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE employee_status (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE language (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE parameters (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(255) NOT NULL, category VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, value VARCHAR(255) NOT NULL, created_by VARCHAR(255) NOT NULL, updated_by VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE room (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, dimension VARCHAR(255) DEFAULT NULL, capability VARCHAR(255) DEFAULT NULL, min SMALLINT DEFAULT NULL, max SMALLINT DEFAULT NULL, computer_number SMALLINT DEFAULT NULL, status SMALLINT NOT NULL, note VARCHAR(255) DEFAULT NULL, suite VARCHAR(40) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE student (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, firstname VARCHAR(255) NOT NULL, start_level VARCHAR(255) DEFAULT NULL, target_level VARCHAR(255) DEFAULT NULL, phone_number VARCHAR(255) DEFAULT NULL, first_email VARCHAR(255) NOT NULL, second_email VARCHAR(255) DEFAULT NULL, start_at DATE NOT NULL, end_at DATE NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, note LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_parameters (id INT AUTO_INCREMENT NOT NULL, parameter_id INT DEFAULT NULL, value VARCHAR(255) NOT NULL, INDEX IDX_A1F48E127C56DBD6 (parameter_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_parameters ADD CONSTRAINT FK_A1F48E127C56DBD6 FOREIGN KEY (parameter_id) REFERENCES parameters (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_parameters DROP FOREIGN KEY FK_A1F48E127C56DBD6');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE closed_contract_info');
        $this->addSql('DROP TABLE contract');
        $this->addSql('DROP TABLE contract_type');
        $this->addSql('DROP TABLE employee');
        $this->addSql('DROP TABLE employee_profile');
        $this->addSql('DROP TABLE employee_status');
        $this->addSql('DROP TABLE language');
        $this->addSql('DROP TABLE parameters');
        $this->addSql('DROP TABLE room');
        $this->addSql('DROP TABLE student');
        $this->addSql('DROP TABLE user_parameters');
    }
}
