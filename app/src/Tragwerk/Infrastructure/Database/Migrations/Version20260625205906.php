<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625205906 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE team_users ADD COLUMN role varchar(16) NOT NULL DEFAULT 'member'");
        $this->addSql("UPDATE team_users tu SET role = 'owner'
            FROM teams t WHERE t.id = tu.team_id AND t.owner_id = tu.user_id");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_users DROP COLUMN role');
    }
}
