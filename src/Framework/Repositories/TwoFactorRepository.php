<?php

declare(strict_types=1);

namespace Framework\Repositories;

use PDO;

/**
 * Two-factor enrollment lookup és perzisztálás. A `Models\TwoFactorModel`
 * static finder metódusait + `save()`/`update()`-jét váltja le.
 *
 * NB. A repository nem köti meg a `TwoFactorModel` osztályt — array-rekordokkal
 * dolgozik, mert az AuthController csak a `secret_key` + `enabled` mezőket
 * vizsgálja. Ha később teljes entity-példányokra van szükség, hozzá lehet adni
 * egy hydratorhívót.
 */
final class TwoFactorRepository
{
    public const METHOD_EMAIL = 'email';
    public const METHOD_APP = 'app';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM two_factor WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUserIdAndMethod(int $userId, string $method): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM two_factor WHERE user_id = :user_id AND method = :method',
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':method', $method, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return list<string> az engedélyezett method-nevek (`'app'`, `'email'`).
     */
    public function enabledMethods(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT method FROM two_factor WHERE user_id = :user_id AND enabled = 1',
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        /** @var list<string> $methods */
        $methods = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_unique($methods));
    }

    public function enroll(int $userId, string $method, string $secretKey, bool $enabled = true): void
    {
        $existing = $this->findByUserIdAndMethod($userId, $method);
        if ($existing === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO two_factor (user_id, method, secret_key, enabled) VALUES (:user_id, :method, :secret_key, :enabled)',
            );
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE two_factor SET secret_key = :secret_key, enabled = :enabled WHERE user_id = :user_id AND method = :method',
            );
        }
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':method', $method, PDO::PARAM_STR);
        $stmt->bindValue(':secret_key', $secretKey, PDO::PARAM_STR);
        $stmt->bindValue(':enabled', $enabled ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function setEnabled(int $userId, string $method, bool $enabled): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE two_factor SET enabled = :enabled WHERE user_id = :user_id AND method = :method',
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':method', $method, PDO::PARAM_STR);
        $stmt->bindValue(':enabled', $enabled ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();
    }
}
