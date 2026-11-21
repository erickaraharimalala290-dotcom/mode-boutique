<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112173154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE return_request (id INT AUTO_INCREMENT NOT NULL, request_number VARCHAR(30) NOT NULL, type VARCHAR(20) NOT NULL, reason VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, quantity INT NOT NULL, status VARCHAR(20) NOT NULL, requested_at DATETIME NOT NULL, processed_at DATETIME DEFAULT NULL, admin_note LONGTEXT DEFAULT NULL, order_item_id INT NOT NULL, UNIQUE INDEX UNIQ_RETURN_REQUEST_NUMBER (request_number), INDEX IDX_2DBF9D40E415FB15 (order_item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE return_request ADD CONSTRAINT FK_2DBF9D40E415FB15 FOREIGN KEY (order_item_id) REFERENCES order_item (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE return_request DROP FOREIGN KEY FK_2DBF9D40E415FB15');
        $this->addSql('DROP TABLE return_request');
    }
}
