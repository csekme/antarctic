<?php

declare(strict_types=1);

namespace Db\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525_010100_CreateRoleAndUserRole extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create role and user_role tables with ROLE_USER + ROLE_ADMIN seed data.';
    }

    public function up(Schema $schema): void
    {
        $role = $schema->createTable('role');
        $role->addColumn('id', 'integer', ['autoincrement' => true]);
        $role->addColumn('uuid', 'string', ['length' => 36]);
        $role->addColumn('name', 'string', ['length' => 45, 'notnull' => false]);
        $role->addColumn('description', 'string', ['length' => 255, 'notnull' => false]);
        $role->setPrimaryKey(['id']);
        $role->addUniqueIndex(['name'], 'UQ_ROLE_NAME');

        $userRole = $schema->createTable('user_role');
        $userRole->addColumn('user_id', 'integer');
        $userRole->addColumn('role_id', 'integer');
        $userRole->setPrimaryKey(['user_id', 'role_id']);
        $userRole->addForeignKeyConstraint(
            'user',
            ['user_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE'],
            'fk_ur_uid',
        );
        $userRole->addForeignKeyConstraint(
            'role',
            ['role_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE'],
            'fk_ur_rid',
        );
    }

    public function postUp(Schema $schema): void
    {
        $this->connection->insert('role', [
            'name' => 'ROLE_USER',
            'uuid' => '52f4e8b1-af53-4057-b28f-70b48746eba6',
            'description' => 'User Role',
        ]);
        $this->connection->insert('role', [
            'name' => 'ROLE_ADMIN',
            'uuid' => '10a4358f-57fa-4313-869f-5da4576a604e',
            'description' => 'Admin Role',
        ]);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('user_role');
        $schema->dropTable('role');
    }
}
