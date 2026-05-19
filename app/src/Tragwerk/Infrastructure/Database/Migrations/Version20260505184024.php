<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505184024 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('create table servers (
                id uuid constraint servers_pk primary key,
                name text not null, 
                host inet not null,
                project_id uuid constraint servers_project_pk references projects(id) on delete cascade,
                created_at timestamp(6) not null,
                created_by uuid not null constraint servers_created_by_fk references users(id),
                updated_at timestamp(6) not null,
                updated_by uuid not null constraint servers_updated_by_fk references users(id)
         )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table servers');
    }
}
