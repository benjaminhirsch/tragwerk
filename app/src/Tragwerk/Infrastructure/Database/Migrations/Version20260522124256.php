<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522124256 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE servers ADD COLUMN docker_version VARCHAR(100) NULL');
        $this->addSql('ALTER TABLE servers ADD COLUMN docker_compose_version VARCHAR(100) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE servers DROP COLUMN docker_version');
        $this->addSql('ALTER TABLE servers DROP COLUMN docker_compose_version');
    }
}
