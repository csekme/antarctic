<?php

declare(strict_types=1);

namespace Framework\Repositories;

use Framework\Models\User;
use PDO;

/**
 * PDO-alapú user lookup. A `Models\AbstractUser` static finder metódusait
 * váltja le — a model tisztán entity-ként marad, a query-k itt élnek.
 *
 * A repository PSR-11 containerből injektálódik (`PDO` autowire-os).
 * Az `AbstractUser` static metódusok továbbra is működnek (lásd
 * `Dal::connection()`), de új kódból már ezt használjuk.
 */
final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?User
    {
        return $this->fetchOne('SELECT * FROM user WHERE id = :id', ['id' => $id]);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->fetchOne('SELECT * FROM user WHERE email = :email', ['email' => $email]);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->fetchOne('SELECT * FROM user WHERE username = :username', ['username' => $username]);
    }

    public function findByUuid(string $uuid): ?User
    {
        return $this->fetchOne('SELECT * FROM user WHERE uuid = :uuid', ['uuid' => $uuid]);
    }

    /**
     * @return list<string>
     */
    public function getRoles(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.name FROM user_role ur LEFT JOIN role r ON ur.role_id = r.id WHERE ur.user_id = :user_id',
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        /** @var list<string> $names */
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $names;
    }

    /**
     * @param array<string, scalar> $params
     */
    private function fetchOne(string $sql, array $params): ?User
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_CLASS, User::class);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $row = $stmt->fetch();
        return $row instanceof User ? $row : null;
    }
}
