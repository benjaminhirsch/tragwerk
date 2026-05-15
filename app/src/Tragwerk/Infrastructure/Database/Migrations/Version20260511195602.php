<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511195602 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('create table projects (
                id uuid constraint projects_pk primary key,
                name text not null,
                owner_id uuid constraint project_owner_pk references users(id),
                created_at timestamp(6) not null,
                updated_at timestamp(6) not null
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table projects');
    }
}
