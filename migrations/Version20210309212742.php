<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210309212742 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE absence_reason (id INT AUTO_INCREMENT NOT NULL, absence_student_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, INDEX IDX_B1BD867FC89AF319 (absence_student_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE absence_student (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, startdate DATETIME NOT NULL, enddate DATETIME NOT NULL, number_day INT NOT NULL, is_no_show TINYINT(1) NOT NULL, is_long TINYINT(1) NOT NULL, status SMALLINT NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(255) NOT NULL, INDEX IDX_7EA58ABBCB944F1A (student_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE activities (id INT AUTO_INCREMENT NOT NULL, ip VARCHAR(255) NOT NULL, session_id INT NOT NULL, rubric VARCHAR(255) NOT NULL, sub_rubric VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE schedule (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, url VARCHAR(255) DEFAULT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE absence_reason ADD CONSTRAINT FK_B1BD867FC89AF319 FOREIGN KEY (absence_student_id) REFERENCES absence_student (id)');
        $this->addSql('ALTER TABLE absence_student ADD CONSTRAINT FK_7EA58ABBCB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE employee CHANGE second_email second_email VARCHAR(255) DEFAULT NULL, CHANGE home_phone_number home_phone_number VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absence_reason DROP FOREIGN KEY FK_B1BD867FC89AF319');
        $this->addSql('DROP TABLE absence_reason');
        $this->addSql('DROP TABLE absence_student');
        $this->addSql('DROP TABLE activities');
        $this->addSql('DROP TABLE schedule');
        $this->addSql('ALTER TABLE employee CHANGE second_email second_email VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE home_phone_number home_phone_number VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`');
    }
}
