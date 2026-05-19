<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519190934 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('create table credentials (
                id uuid constraint credentials_pk primary key,
                username text not null, 
                password text default null,
                private_key text default null,                
                created_at timestamp(6) not null,
                created_by uuid not null constraint credentials_created_by_fk references users(id),
                updated_at timestamp(6) not null,
                updated_by uuid not null constraint credentials_updated_by_fk references users(id)
         )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('credentials');
    }
}
