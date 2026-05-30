<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530200000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE project_domains
                ADD COLUMN branch VARCHAR(255) NOT NULL DEFAULT 'main'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE project_domains
                DROP CONSTRAINT IF EXISTS project_domains_project_id_host_key
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE project_domains
                ADD CONSTRAINT project_domains_project_id_branch_host_key
                UNIQUE (project_id, branch, host)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE project_domains
                DROP CONSTRAINT IF EXISTS project_domains_project_id_branch_host_key
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE project_domains
                ADD CONSTRAINT project_domains_project_id_host_key
                UNIQUE (project_id, host)
            SQL);

        $this->addSql('ALTER TABLE project_domains DROP COLUMN branch');
    }
}
