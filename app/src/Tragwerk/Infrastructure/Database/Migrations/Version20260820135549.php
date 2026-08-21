<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820135549 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // A NULL branch marks a project-scoped variable: it applies to every environment and has
        // the lowest precedence. Without it, variables can only be configured after the first push
        // has created a branch — which is exactly when the first build already needs them.
        $this->addSql('ALTER TABLE project_env_vars ALTER COLUMN branch DROP NOT NULL');

        // The original UNIQUE (project_id, branch, key) was declared inline, so its name was
        // assigned by Postgres. Resolve it instead of guessing, then re-add it with NULLS NOT
        // DISTINCT (Postgres 15+) — otherwise two project-scoped rows with the same key would both
        // be accepted, since NULLs compare as distinct by default.
        $this->addSql(<<<'SQL'
            DO $$
            DECLARE constraint_name text;
            BEGIN
                SELECT conname INTO constraint_name
                  FROM pg_constraint
                 WHERE conrelid = 'project_env_vars'::regclass
                   AND contype = 'u';

                IF constraint_name IS NOT NULL THEN
                    EXECUTE format('ALTER TABLE project_env_vars DROP CONSTRAINT %I', constraint_name);
                END IF;
            END $$
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE project_env_vars
                ADD CONSTRAINT project_env_vars_project_branch_key
                UNIQUE NULLS NOT DISTINCT (project_id, branch, key)
        SQL);

        // is_inherited means "pass down to child branches" and is meaningless for a variable that
        // already applies everywhere.
        $this->addSql(<<<'SQL'
            ALTER TABLE project_env_vars
                ADD CONSTRAINT project_env_vars_inherited_needs_branch
                CHECK (branch IS NOT NULL OR is_inherited = false)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_env_vars DROP CONSTRAINT project_env_vars_inherited_needs_branch');
        $this->addSql('ALTER TABLE project_env_vars DROP CONSTRAINT project_env_vars_project_branch_key');

        // Project-scoped rows cannot survive the NOT NULL that follows.
        $this->addSql('DELETE FROM project_env_vars WHERE branch IS NULL');

        $this->addSql('ALTER TABLE project_env_vars ALTER COLUMN branch SET NOT NULL');
        $this->addSql('ALTER TABLE project_env_vars ADD UNIQUE (project_id, branch, key)');
    }
}
