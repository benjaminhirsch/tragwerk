<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522130306 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE servers ADD COLUMN port INTEGER NOT NULL DEFAULT 22');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE servers DROP COLUMN port');
    }
}
