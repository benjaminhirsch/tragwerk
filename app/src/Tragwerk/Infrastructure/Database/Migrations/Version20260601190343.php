<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the session cache and lock tables explicitly.
 *
 * These are otherwise auto-created at runtime by symfony/cache's DoctrineDbalAdapter
 * ('sessions') and symfony/lock's DoctrineDbalStore ('session_locks'). Relying on that
 * lazy creation is fragile on PostgreSQL when the first access happens inside an open
 * transaction (a TableNotFoundException aborts the whole transaction), so we provision
 * them up front. IF NOT EXISTS keeps this safe where the tables were already auto-created.
 */
final class Version20260601190343 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sessions (
                item_id       VARCHAR(255) NOT NULL,
                item_data     BYTEA        NOT NULL,
                item_lifetime INTEGER          NULL,
                item_time     INTEGER      NOT NULL,
                PRIMARY KEY (item_id)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS session_locks (
                key_id         VARCHAR(64) NOT NULL,
                key_token      VARCHAR(44) NOT NULL,
                key_expiration INTEGER     NOT NULL,
                PRIMARY KEY (key_id)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS session_locks');
        $this->addSql('DROP TABLE IF EXISTS sessions');
    }
}
