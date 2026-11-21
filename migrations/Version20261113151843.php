<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113151843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE return_request ADD replacement_unit_price NUMERIC(12, 2) DEFAULT NULL, ADD price_difference NUMERIC(12, 2) DEFAULT NULL, ADD replacement_product_id INT DEFAULT NULL, ADD replacement_variant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE return_request ADD CONSTRAINT FK_2DBF9D40847302E0 FOREIGN KEY (replacement_product_id) REFERENCES product (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE return_request ADD CONSTRAINT FK_2DBF9D40FA9ECD15 FOREIGN KEY (replacement_variant_id) REFERENCES product_variant (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_2DBF9D40847302E0 ON return_request (replacement_product_id)');
        $this->addSql('CREATE INDEX IDX_2DBF9D40FA9ECD15 ON return_request (replacement_variant_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE return_request DROP FOREIGN KEY FK_2DBF9D40847302E0');
        $this->addSql('ALTER TABLE return_request DROP FOREIGN KEY FK_2DBF9D40FA9ECD15');
        $this->addSql('DROP INDEX IDX_2DBF9D40847302E0 ON return_request');
        $this->addSql('DROP INDEX IDX_2DBF9D40FA9ECD15 ON return_request');
        $this->addSql('ALTER TABLE return_request DROP replacement_unit_price, DROP price_difference, DROP replacement_product_id, DROP replacement_variant_id');
    }
}
