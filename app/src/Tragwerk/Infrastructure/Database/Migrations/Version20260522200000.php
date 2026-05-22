<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522200000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE projects RENAME TO teams');
        $this->addSql('ALTER TABLE project_invitations RENAME TO team_invitations');
        $this->addSql('ALTER TABLE project_users RENAME TO team_users');

        $this->addSql('ALTER TABLE team_invitations RENAME COLUMN project_id TO team_id');
        $this->addSql('ALTER TABLE team_users RENAME COLUMN project_id TO team_id');
        $this->addSql('ALTER TABLE servers RENAME COLUMN project_id TO team_id');
        $this->addSql('ALTER TABLE credentials RENAME COLUMN project_id TO team_id');
        $this->addSql('ALTER TABLE users RENAME COLUMN last_active_project_id TO last_active_team_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users RENAME COLUMN last_active_team_id TO last_active_project_id');
        $this->addSql('ALTER TABLE credentials RENAME COLUMN team_id TO project_id');
        $this->addSql('ALTER TABLE servers RENAME COLUMN team_id TO project_id');
        $this->addSql('ALTER TABLE team_users RENAME COLUMN team_id TO project_id');
        $this->addSql('ALTER TABLE team_invitations RENAME COLUMN team_id TO project_id');

        $this->addSql('ALTER TABLE team_users RENAME TO project_users');
        $this->addSql('ALTER TABLE team_invitations RENAME TO project_invitations');
        $this->addSql('ALTER TABLE teams RENAME TO projects');
    }
}
