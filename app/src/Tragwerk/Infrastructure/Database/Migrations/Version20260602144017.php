<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602144017 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE registries (
                id         UUID         NOT NULL PRIMARY KEY,
                name       TEXT         NOT NULL,
                url        TEXT         NOT NULL,
                username   TEXT         NOT NULL,
                password   TEXT         NOT NULL,
                team_id    UUID         NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
                created_at TIMESTAMPTZ  NOT NULL,
                created_by UUID         NOT NULL REFERENCES users(id),
                updated_at TIMESTAMPTZ  NOT NULL,
                updated_by UUID         NOT NULL REFERENCES users(id)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS registries');
    }
}
