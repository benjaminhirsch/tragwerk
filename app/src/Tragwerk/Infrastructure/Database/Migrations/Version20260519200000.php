<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519200000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE servers ADD COLUMN credential_id uuid DEFAULT NULL');
        $this->addSql(
            'ALTER TABLE servers ADD CONSTRAINT servers_credential_id_fkey'
            . ' FOREIGN KEY (credential_id) REFERENCES credentials(id) ON DELETE RESTRICT',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE servers DROP CONSTRAINT servers_credential_id_fkey');
        $this->addSql('ALTER TABLE servers DROP COLUMN credential_id');
    }
}
