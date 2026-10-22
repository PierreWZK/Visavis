<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021145551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE brand (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, UNIQUE INDEX UNIQ_1C52F9585E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE car_model (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, body VARCHAR(255) NOT NULL, seats SMALLINT NOT NULL, brand_id INT NOT NULL, INDEX IDX_83EF70E44F5D008 (brand_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE version (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, year SMALLINT NOT NULL, energy VARCHAR(255) NOT NULL, price INT NOT NULL, power SMALLINT NOT NULL, consumption DOUBLE PRECISION NOT NULL, trunk SMALLINT NOT NULL, co2 SMALLINT NOT NULL, model_id INT NOT NULL, INDEX IDX_BF1CD3C37975B7E7 (model_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE car_model ADD CONSTRAINT FK_83EF70E44F5D008 FOREIGN KEY (brand_id) REFERENCES brand (id)');
        $this->addSql('ALTER TABLE version ADD CONSTRAINT FK_BF1CD3C37975B7E7 FOREIGN KEY (model_id) REFERENCES car_model (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE car_model DROP FOREIGN KEY FK_83EF70E44F5D008');
        $this->addSql('ALTER TABLE version DROP FOREIGN KEY FK_BF1CD3C37975B7E7');
        $this->addSql('DROP TABLE brand');
        $this->addSql('DROP TABLE car_model');
        $this->addSql('DROP TABLE version');
    }
}
