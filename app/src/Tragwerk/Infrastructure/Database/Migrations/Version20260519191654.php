<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519191654 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE credentials ADD COLUMN name text NOT NULL DEFAULT ''");
        $this->addSql('ALTER TABLE credentials ALTER COLUMN name DROP DEFAULT');
        $this->addSql('ALTER TABLE credentials ADD COLUMN project_id uuid 
            NOT NULL REFERENCES projects(id) ON DELETE CASCADE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credentials DROP COLUMN name');
        $this->addSql('ALTER TABLE credentials DROP COLUMN project_id');
    }
}
