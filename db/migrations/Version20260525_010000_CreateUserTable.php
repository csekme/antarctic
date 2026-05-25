<?php

declare(strict_types=1);

namespace Db\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Létrehozza az alap `user` táblát + unique indexeit. Megfelel a
 * docker/mariadb/init.sql kézi sémájának, de platform-független
 * doctrine/dbal Schema API-val.
 */
final class Version20260525_010000_CreateUserTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create base user table with unique constraints (uuid/username/email).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('user');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('uuid', 'string', ['length' => 36]);
        $table->addColumn('username', 'string', ['length' => 45, 'notnull' => false]);
        $table->addColumn('firstname', 'string', ['length' => 45, 'notnull' => false]);
        $table->addColumn('lastname', 'string', ['length' => 45, 'notnull' => false]);
        $table->addColumn('email', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('password_hash', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('activation_hash', 'string', ['length' => 64, 'notnull' => false]);
        $table->addColumn('is_active', 'smallint', ['default' => 0]);
        $table->addColumn('password_reset_hash', 'string', ['length' => 64, 'notnull' => false]);
        $table->addColumn('password_reset_expires_at', 'datetime', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => false]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'UQ_USER_UUID');
        $table->addUniqueIndex(['username'], 'UQ_USERNAME');
        $table->addUniqueIndex(['email'], 'UQ_EMAIL');
        $table->addUniqueIndex(['password_reset_hash'], 'UQ_PASSWORD_RESET_HASH');
        $table->addUniqueIndex(['activation_hash'], 'UQ_ACTIVATION_HASH');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('user');
    }
}
