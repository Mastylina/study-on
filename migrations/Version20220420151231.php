<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220420151231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE course_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE lesson_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE course (id INT NOT NULL, character_code VARCHAR(255) NOT NULL, name_course VARCHAR(255) NOT NULL, description VARCHAR(1000) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_169E6FB960E92400 ON course (character_code)');
        $this->addSql('CREATE TABLE lesson (id INT NOT NULL, cours_lesson_id INT NOT NULL, name_lesson VARCHAR(255) NOT NULL, content_lesson TEXT NOT NULL, number_lesson INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F87474F3355D3B51 ON lesson (cours_lesson_id)');
        $this->addSql('ALTER TABLE lesson ADD CONSTRAINT FK_F87474F3355D3B51 FOREIGN KEY (cours_lesson_id) REFERENCES course (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lesson DROP CONSTRAINT FK_F87474F3355D3B51');
        $this->addSql('DROP SEQUENCE course_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE lesson_id_seq CASCADE');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE lesson');
    }
}
