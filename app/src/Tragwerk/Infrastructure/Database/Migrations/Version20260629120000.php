<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Persist the FrankenPHP worker crash/restart counters in app_metrics so the environment KPI tiles
 * can be served from the database (via getLatest) instead of a live SSH call on every poll. Existing
 * rows default to 0.
 */
final class Version20260629120000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE app_metrics
                ADD COLUMN crashes  INT NOT NULL DEFAULT 0,
                ADD COLUMN restarts INT NOT NULL DEFAULT 0
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_metrics DROP COLUMN crashes, DROP COLUMN restarts');
    }
}
