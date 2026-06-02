<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602200241 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registries ADD COLUMN pruning_enabled BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE registries ADD COLUMN keep_tags INTEGER NOT NULL DEFAULT 10');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registries DROP COLUMN IF EXISTS pruning_enabled');
        $this->addSql('ALTER TABLE registries DROP COLUMN IF EXISTS keep_tags');
    }
}
