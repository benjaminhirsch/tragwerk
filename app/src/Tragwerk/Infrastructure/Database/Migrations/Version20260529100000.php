<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529100000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE deploy_jobs (
                id         VARCHAR(36)  NOT NULL PRIMARY KEY,
                project_id VARCHAR(36)  NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                branch     VARCHAR(255) NOT NULL,
                commit_sha VARCHAR(40)  NOT NULL,
                status     VARCHAR(20)  NOT NULL DEFAULT 'pending',
                output     TEXT         NOT NULL DEFAULT '',
                created_at TIMESTAMP(6) NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP(6) NOT NULL DEFAULT NOW()
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE deploy_jobs');
    }
}
