<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505184400 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('create table project_users
            (
                project_id    uuid constraint on_stories_pk references projects(id) on delete cascade,
                user_id     uuid constraint on_users_pk references users(id) on delete cascade,
                assigned_at timestamp(6) not null,
                constraint project_users_pk primary key (project_id, user_id)
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table project_users');
    }
}
