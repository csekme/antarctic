<?php

declare(strict_types=1);

namespace Db\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525_010200_CreateTwoFactorTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create two_factor table for TOTP / email-OTP enrollment.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('two_factor');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('user_id', 'integer');
        $table->addColumn('method', 'string', ['length' => 15, 'notnull' => false]);
        $table->addColumn('secret_key', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('passcode', 'string', ['length' => 6, 'notnull' => false]);
        $table->addColumn('enabled', 'smallint', ['default' => 0]);
        $table->addColumn('passcode_expired_at', 'datetime', ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['user_id', 'method'], 'UQ_TWO_FACTOR_USER_METHOD');
        $table->addForeignKeyConstraint(
            'user',
            ['user_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE'],
            'fk_two_factor_user_id',
        );
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('two_factor');
    }
}
