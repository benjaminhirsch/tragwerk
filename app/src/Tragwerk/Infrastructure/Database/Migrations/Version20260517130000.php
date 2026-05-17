<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517130000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('create table project_invitations (
            id          uuid        constraint project_invitations_pk primary key,
            project_id  uuid        not null
                constraint project_invitations_project_fk references projects(id) on delete cascade,
            email       text        not null,
            token       uuid        not null unique,
            invited_at  timestamp(6) not null,
            invited_by  uuid        not null constraint project_invitations_invited_by_fk references users(id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table project_invitations');
    }
}
