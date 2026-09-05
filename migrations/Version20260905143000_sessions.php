<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905143000_sessions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sessions table with generated civil day uniqueness and occupancy check';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sessions (
                id UUID NOT NULL,
                experience_id UUID NOT NULL,
                starts_at TIMESTAMPTZ NOT NULL,
                capacity INT NOT NULL,
                seats_taken INT NOT NULL,
                price JSONB NOT NULL,
                timezone VARCHAR(64) NOT NULL,
                session_day DATE GENERATED ALWAYS AS ((starts_at AT TIME ZONE timezone)::date) STORED,
                PRIMARY KEY (id),
                CONSTRAINT uniq_sessions_experience_id_session_day UNIQUE (experience_id, session_day),
                CONSTRAINT chk_sessions_seats_taken CHECK (seats_taken >= 0 AND seats_taken <= capacity)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE FUNCTION sessions_copy_experience_timezone() RETURNS trigger AS $$
            BEGIN
                SELECT timezone INTO STRICT NEW.timezone FROM experiences WHERE id = NEW.experience_id;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER sessions_copy_experience_timezone
            BEFORE INSERT OR UPDATE OF experience_id ON sessions
            FOR EACH ROW
            EXECUTE FUNCTION sessions_copy_experience_timezone()
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sessions');
        $this->addSql('DROP FUNCTION sessions_copy_experience_timezone()');
    }
}
