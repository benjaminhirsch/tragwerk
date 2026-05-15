<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505184024 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('create table stories (
                id uuid constraint stories_pk primary key,
                story_type text not null, 
                points int default null,
                requester_id uuid constraint on_users_pk references users(id),
                created_at timestamp(6) not null,
                updated_at timestamp(6) not null
         )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table stories');
    }
}
