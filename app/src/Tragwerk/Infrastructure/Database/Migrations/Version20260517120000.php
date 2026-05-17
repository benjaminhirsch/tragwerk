<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517120000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('alter table projects
            add column created_by uuid,
            add column updated_by uuid
        ');
        $this->addSql('update projects set created_by = owner_id, updated_by = owner_id');
        $this->addSql('alter table projects
            alter column created_by set not null,
            alter column updated_by set not null
        ');
        $this->addSql('alter table projects
            add constraint projects_created_by_fk foreign key (created_by) references users(id),
            add constraint projects_updated_by_fk foreign key (updated_by) references users(id)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('alter table projects
            drop column created_by,
            drop column updated_by
        ');
    }
}
