<?php

declare(strict_types=1);

namespace Db\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A korábban (M2.a-ben) kézzel írt SQL-fájlok hivatalos doctrine-migration
 * megfelelője. A séma azonos a `db/migrations/{mariadb,postgresql}/001_...sql`
 * fájlokéval — a PR egyúttal törli is a két régi `.sql`-t.
 */
final class Version20260525_010300_CreateRefreshTokens extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create refresh_tokens table for JWT refresh rotation + reuse detection.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('refresh_tokens');
        $table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true]);
        $table->addColumn('user_id', 'bigint', ['unsigned' => true]);
        $table->addColumn('family_id', 'string', ['length' => 64]);
        $table->addColumn('token_hash', 'string', ['length' => 64, 'fixed' => true]);
        $table->addColumn('rotated_from', 'bigint', ['unsigned' => true, 'notnull' => false]);
        $table->addColumn('expires_at', 'datetime');
        $table->addColumn('revoked_at', 'datetime', ['notnull' => false]);
        $table->addColumn('user_agent', 'text', ['notnull' => false]);
        $table->addColumn('ip', 'string', ['length' => 45, 'notnull' => false]);
        $table->addColumn('created_at', 'datetime');

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['token_hash'], 'refresh_tokens_hash_unique');
        $table->addIndex(['user_id'], 'refresh_tokens_user_id_idx');
        $table->addIndex(['family_id'], 'refresh_tokens_family_id_idx');
        $table->addIndex(['expires_at'], 'refresh_tokens_expires_idx');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('refresh_tokens');
    }
}
