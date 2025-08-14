<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250814090708 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE books (id SERIAL NOT NULL, title VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL, in_stock BOOLEAN NOT NULL, product_url VARCHAR(512) NOT NULL, image_url VARCHAR(512) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4A1B2A92145911B1 ON books (product_url)');
        $this->addSql('CREATE INDEX idx_books_price ON books (price)');
        $this->addSql('CREATE INDEX idx_books_in_stock ON books (in_stock)');
        $this->addSql('COMMENT ON COLUMN books.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE books');
    }
}
