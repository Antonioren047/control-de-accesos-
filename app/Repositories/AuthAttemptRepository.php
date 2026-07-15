<?php
declare(strict_types=1);
namespace Vigilancia\Repositories;

use PDO;
use Vigilancia\Auth\BackoffPolicy;

final class AuthAttemptRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(string $identifierHash, string $ip): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM auth_attempts WHERE identifier_hash=? AND ip_address=? LIMIT 1"
        );
        $statement->execute([$identifierHash, $ip]);
        return $statement->fetch() ?: null;
    }

    public function registerFailure(string $identifierHash, string $ip, int $base, int $maximum): array
    {
        $current = $this->find($identifierHash, $ip);
        $attempts = (int) ($current['failed_attempts'] ?? 0) + 1;
        $seconds = BackoffPolicy::seconds($attempts, $base, $maximum);
        $blockedUntil = $seconds > 0 ? gmdate('Y-m-d H:i:s', time() + $seconds) : null;
        $statement = $this->pdo->prepare(
            "INSERT INTO auth_attempts(identifier_hash,ip_address,failed_attempts,blocked_until,last_attempt_at,created_at,updated_at)
             VALUES(?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE failed_attempts=VALUES(failed_attempts),blocked_until=VALUES(blocked_until),
               last_attempt_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP()"
        );
        $statement->execute([$identifierHash, $ip, $attempts, $blockedUntil]);
        return ['attempts' => $attempts, 'blocked_until' => $blockedUntil, 'seconds' => $seconds];
    }

    public function clear(string $identifierHash, string $ip): void
    {
        $this->pdo->prepare(
            "DELETE FROM auth_attempts WHERE identifier_hash=? AND ip_address=?"
        )->execute([$identifierHash, $ip]);
    }
}
