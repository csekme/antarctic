<?php

declare(strict_types=1);

namespace Tests\Framework\Repositories;

use Framework\Models\User;
use Framework\Repositories\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class UserRepositoryTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE user (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT,
                username TEXT,
                email TEXT,
                password_hash TEXT,
                is_active INTEGER DEFAULT 0
            );
        SQL);
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE role (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE
            );
        SQL);
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE user_role (
                user_id INTEGER,
                role_id INTEGER
            );
        SQL);
        $this->pdo->exec("INSERT INTO user (uuid, username, email, is_active) VALUES ('u-1', 'alice', 'alice@example.com', 1)");
        $this->pdo->exec("INSERT INTO user (uuid, username, email, is_active) VALUES ('u-2', 'bob', 'bob@example.com', 1)");
        $this->pdo->exec("INSERT INTO role (id, name) VALUES (1, 'ROLE_USER'), (2, 'ROLE_ADMIN')");
        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES (1, 1), (1, 2), (2, 1)");

        $this->repo = new UserRepository($this->pdo);
    }

    public function testFindById(): void
    {
        $user = $this->repo->findById(1);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('alice', $user->username);
    }

    public function testFindByIdMissingReturnsNull(): void
    {
        $this->assertNull($this->repo->findById(999));
    }

    public function testFindByEmail(): void
    {
        $user = $this->repo->findByEmail('bob@example.com');
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('bob', $user->username);
    }

    public function testFindByUsername(): void
    {
        $user = $this->repo->findByUsername('alice');
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('alice@example.com', $user->email);
    }

    public function testFindByUuid(): void
    {
        $user = $this->repo->findByUuid('u-2');
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(2, (int) $user->id);
    }

    public function testGetRoles(): void
    {
        $this->assertSame(['ROLE_USER', 'ROLE_ADMIN'], $this->repo->getRoles(1));
        $this->assertSame(['ROLE_USER'], $this->repo->getRoles(2));
        $this->assertSame([], $this->repo->getRoles(999));
    }
}
