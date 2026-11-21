<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027144341 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE promotion (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, code VARCHAR(50) DEFAULT NULL, discount_type VARCHAR(20) NOT NULL, discount_value NUMERIC(12, 2) NOT NULL, starts_at DATETIME DEFAULT NULL, ends_at DATETIME NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_PROMOTION_CODE (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE customer_order ADD promotion_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT FK_3B1CE6A3139DF194 FOREIGN KEY (promotion_id) REFERENCES promotion (id)');
        $this->addSql('CREATE INDEX IDX_3B1CE6A3139DF194 ON customer_order (promotion_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_3B1CE6A3139DF194');
        $this->addSql('DROP INDEX IDX_3B1CE6A3139DF194 ON customer_order');
        $this->addSql('ALTER TABLE customer_order DROP promotion_id');
        $this->addSql('DROP TABLE promotion');
    }
}
