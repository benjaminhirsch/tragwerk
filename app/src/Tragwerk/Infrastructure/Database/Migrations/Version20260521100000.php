<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521100000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE setup_jobs ('
            . 'id uuid NOT NULL PRIMARY KEY,'
            . 'server_id uuid NOT NULL,'
            . 'status varchar(20) NOT NULL DEFAULT \'pending\','
            . 'output text NOT NULL DEFAULT \'\','
            . 'created_at timestamp with time zone NOT NULL,'
            . 'updated_at timestamp with time zone NOT NULL,'
            . 'CONSTRAINT setup_jobs_server_id_fkey FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE'
            . ')',
        );
        $this->addSql('CREATE INDEX setup_jobs_server_id_idx ON setup_jobs (server_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE setup_jobs');
    }
}
