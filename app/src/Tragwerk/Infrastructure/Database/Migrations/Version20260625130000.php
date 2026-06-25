<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260625130000 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        // When set, the confirmation applies a pending email change instead of a
        // first-time registration confirmation.
        $this->addSql('ALTER TABLE email_confirmations ADD COLUMN new_email TEXT DEFAULT NULL');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_confirmations DROP COLUMN new_email');
    }
}
