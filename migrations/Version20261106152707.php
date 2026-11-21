<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261106152707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE message_reply (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, contact_message_id INT NOT NULL, sender_id INT NOT NULL, INDEX IDX_20E390B94C34ABE (contact_message_id), INDEX IDX_20E390BF624B39D (sender_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE message_reply ADD CONSTRAINT FK_20E390B94C34ABE FOREIGN KEY (contact_message_id) REFERENCES contact_message (id)');
        $this->addSql('ALTER TABLE message_reply ADD CONSTRAINT FK_20E390BF624B39D FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE contact_message ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_message ADD CONSTRAINT FK_2C9211FEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_2C9211FEA76ED395 ON contact_message (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE message_reply DROP FOREIGN KEY FK_20E390B94C34ABE');
        $this->addSql('ALTER TABLE message_reply DROP FOREIGN KEY FK_20E390BF624B39D');
        $this->addSql('DROP TABLE message_reply');
        $this->addSql('ALTER TABLE contact_message DROP FOREIGN KEY FK_2C9211FEA76ED395');
        $this->addSql('DROP INDEX IDX_2C9211FEA76ED395 ON contact_message');
        $this->addSql('ALTER TABLE contact_message DROP user_id');
    }
}
