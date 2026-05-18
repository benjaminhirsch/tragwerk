<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518202033 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE servers ADD CONSTRAINT servers_host_unique UNIQUE (host)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE servers DROP CONSTRAINT servers_host_unique');
    }
}
