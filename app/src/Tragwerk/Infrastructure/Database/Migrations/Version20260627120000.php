<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domains move from environment scope to project scope: drop the branch column, add an
 * is_wildcard flag and make (project_id, host) unique again.
 */
final class Version20260627120000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE project_domains
                ADD COLUMN is_wildcard BOOLEAN NOT NULL DEFAULT false
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE project_domains
                DROP CONSTRAINT IF EXISTS project_domains_project_id_branch_host_key
            SQL);

        $this->addSql('ALTER TABLE project_domains DROP COLUMN branch');

        $this->addSql(<<<'SQL'
            ALTER TABLE project_domains
                ADD CONSTRAINT project_domains_project_id_host_key
                UNIQUE (project_id, host)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE project_domains
                DROP CONSTRAINT IF EXISTS project_domains_project_id_host_key
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE project_domains
                ADD COLUMN branch VARCHAR(255) NOT NULL DEFAULT 'main'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE project_domains
                ADD CONSTRAINT project_domains_project_id_branch_host_key
                UNIQUE (project_id, branch, host)
            SQL);

        $this->addSql('ALTER TABLE project_domains DROP COLUMN is_wildcard');
    }
}
