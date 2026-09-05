<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905115100_experiences extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create experiences table with UUID primary key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE experiences (
                id UUID NOT NULL,
                provider_id UUID NOT NULL,
                title VARCHAR(150) NOT NULL,
                description TEXT NOT NULL,
                timezone VARCHAR(64) NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE experiences');
    }
}
