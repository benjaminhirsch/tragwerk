<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260405200049 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('create table users (
            id uuid constraint users_pk primary key,
            email text not null,
            firstname text not null,
            lastname text not null,
            displayname text default null,
            password text not null,            
            created_at timestamp(6) not null,
            updated_at timestamp(6) not null
        )');
    }
}
