<?php

declare(strict_types=1);

namespace Framework\Auth;

use DateTimeImmutable;
use PDO;

/**
 * `refresh_tokens` tábla CRUD. Csak prepared statementek, sehol nincs
 * string-interpoláció a query-ben. SQL séma kompatibilis sqlite +
 * postgres + mariadb-vel (a `DATETIME` típust ISO 8601 stringként
 * használjuk minden driverben).
 */
final class RefreshTokenRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function store(
        int $userId,
        string $familyId,
        string $tokenHash,
        ?int $rotatedFrom,
        DateTimeImmutable $expiresAt,
        ?string $userAgent,
        ?string $ip,
    ): int {
        $sql = 'INSERT INTO refresh_tokens '
            . '(user_id, family_id, token_hash, rotated_from, expires_at, user_agent, ip, created_at) '
            . 'VALUES (:user_id, :family_id, :token_hash, :rotated_from, :expires_at, :user_agent, :ip, :created_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':family_id' => $familyId,
            ':token_hash' => $tokenHash,
            ':rotated_from' => $rotatedFrom,
            ':expires_at' => $expiresAt->format(DATE_ATOM),
            ':user_agent' => $userAgent,
            ':ip' => $ip,
            ':created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array{
     *   id: int,
     *   user_id: int,
     *   family_id: string,
     *   token_hash: string,
     *   rotated_from: ?int,
     *   expires_at: string,
     *   revoked_at: ?string,
     *   user_agent: ?string,
     *   ip: ?string,
     *   created_at: string,
     * }|null
     */
    public function findByHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM refresh_tokens WHERE token_hash = :h LIMIT 1');
        $stmt->execute([':h' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        /** @var array{id: int, user_id: int, family_id: string, token_hash: string, rotated_from: ?int, expires_at: string, revoked_at: ?string, user_agent: ?string, ip: ?string, created_at: string} $row */
        return $row;
    }

    public function markRotated(int $id, DateTimeImmutable $at): void
    {
        $stmt = $this->pdo->prepare('UPDATE refresh_tokens SET revoked_at = :at WHERE id = :id AND revoked_at IS NULL');
        $stmt->execute([':at' => $at->format(DATE_ATOM), ':id' => $id]);
    }

    /**
     * Egész family-t revokál (lopás-gyanú esetén az összes leszármazott
     * tokent érvényteleníti).
     *
     * @return int Hány sort revokált.
     */
    public function revokeFamily(string $familyId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE refresh_tokens SET revoked_at = :at WHERE family_id = :fid AND revoked_at IS NULL'
        );
        $stmt->execute([
            ':at' => (new DateTimeImmutable())->format(DATE_ATOM),
            ':fid' => $familyId,
        ]);
        return $stmt->rowCount();
    }

    /**
     * Lejárt vagy régen revokált tokenek törlése. Cronból futtatandó.
     */
    public function purgeExpired(DateTimeImmutable $before): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM refresh_tokens WHERE expires_at < :b OR (revoked_at IS NOT NULL AND revoked_at < :b)'
        );
        $stmt->execute([':b' => $before->format(DATE_ATOM)]);
        return $stmt->rowCount();
    }
}
