<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825120000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // Per-project entropy exposed to deployed applications as TRAGWERK_PROJECT_ENTROPY. It has
        // to survive every deploy, environment and container rebuild, so it lives in the database
        // rather than being derived at generation time.
        $this->addSql('ALTER TABLE projects ADD COLUMN entropy VARCHAR(64)');

        // Backfill without depending on pgcrypto: two md5() halves produce the same 64 hex
        // characters Token::generate(32) emits for new projects.
        $this->addSql(<<<'SQL'
            UPDATE projects
               SET entropy = md5(random()::text || clock_timestamp()::text || id::text)
                          || md5(random()::text || clock_timestamp()::text || id::text)
             WHERE entropy IS NULL
        SQL);

        $this->addSql('ALTER TABLE projects ALTER COLUMN entropy SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE projects DROP COLUMN entropy');
    }
}
