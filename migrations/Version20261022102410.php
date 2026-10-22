<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261022102410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalogue ADEME : valeurs inconnues à null, poids, autonomie, boîte, source de chaque version';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE car_model CHANGE seats seats SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE version ADD weight SMALLINT DEFAULT NULL, ADD electric_range SMALLINT DEFAULT NULL, ADD gearbox VARCHAR(255) DEFAULT NULL, ADD source VARCHAR(10) NOT NULL, ADD source_key VARCHAR(40) DEFAULT NULL, CHANGE year year SMALLINT DEFAULT NULL, CHANGE consumption consumption DOUBLE PRECISION DEFAULT NULL, CHANGE trunk trunk SMALLINT DEFAULT NULL, CHANGE co2 co2 SMALLINT DEFAULT NULL');
        // Les versions déjà en base sont celles du catalogue de démonstration
        $this->addSql("UPDATE version SET source = 'demo'");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BF1CD3C3DB64AEE8 ON version (source_key)');
        $this->addSql('CREATE INDEX IDX_BF1CD3C35F8A7F73 ON version (source)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE car_model CHANGE seats seats SMALLINT NOT NULL');
        $this->addSql('DROP INDEX UNIQ_BF1CD3C3DB64AEE8 ON version');
        $this->addSql('DROP INDEX IDX_BF1CD3C35F8A7F73 ON version');
        $this->addSql('ALTER TABLE version DROP weight, DROP electric_range, DROP gearbox, DROP source, DROP source_key, CHANGE year year SMALLINT NOT NULL, CHANGE consumption consumption DOUBLE PRECISION NOT NULL, CHANGE trunk trunk SMALLINT NOT NULL, CHANGE co2 co2 SMALLINT NOT NULL');
    }
}
