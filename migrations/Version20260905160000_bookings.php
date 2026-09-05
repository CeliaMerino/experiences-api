<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905160000_bookings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bookings table with index on session_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE bookings (
                id UUID NOT NULL,
                session_id UUID NOT NULL,
                user_id UUID NOT NULL,
                seats INT NOT NULL,
                total JSONB NOT NULL,
                status VARCHAR(32) NOT NULL,
                contact_email VARCHAR(255) NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $this->addSql('CREATE INDEX idx_bookings_session_id ON bookings (session_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bookings');
    }
}
