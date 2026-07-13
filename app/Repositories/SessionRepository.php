<?php
declare(strict_types=1);
namespace Vigilancia\Repositories;

use PDO;

final class SessionRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(int $userId, string $tokenHash, string $ip, string $userAgent, int $hours): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO user_sessions(user_id,session_token_hash,ip_address,user_agent,last_activity_at,expires_at,created_at)
             VALUES(?,?,?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? HOUR),UTC_TIMESTAMP())"
        );
        $statement->execute([$userId, $tokenHash, $ip, $userAgent, $hours]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findActive(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT s.id AS auth_session_id,s.expires_at,u.*,r.code AS role_code,r.name AS role_name,
                    c.name AS company_name
             FROM user_sessions s
             JOIN users u ON u.id=s.user_id
             JOIN roles r ON r.id=u.role_id
             JOIN surveillance_companies c ON c.id=u.surveillance_company_id
             WHERE s.session_token_hash=? AND s.revoked_at IS NULL AND s.expires_at>UTC_TIMESTAMP()
               AND u.is_active=1 AND r.is_active=1 AND c.is_active=1
             LIMIT 1"
        );
        $statement->execute([$tokenHash]);
        return $statement->fetch() ?: null;
    }

    public function touch(int $sessionId): void
    {
        $this->pdo->prepare(
            "UPDATE user_sessions SET last_activity_at=UTC_TIMESTAMP() WHERE id=? AND revoked_at IS NULL"
        )->execute([$sessionId]);
    }

    public function revoke(int $sessionId): void
    {
        $this->pdo->prepare(
            "UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE id=? AND revoked_at IS NULL"
        )->execute([$sessionId]);
    }

    public function revokeAll(int $userId, ?int $exceptSessionId = null): void
    {
        $sql = "UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND revoked_at IS NULL";
        $parameters = [$userId];
        if ($exceptSessionId !== null) {
            $sql .= ' AND id<>?';
            $parameters[] = $exceptSessionId;
        }
        $this->pdo->prepare($sql)->execute($parameters);
    }

    public function enforceLimit(int $userId, int $maximum): void
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM user_sessions
             WHERE user_id=? AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP()
             ORDER BY last_activity_at DESC"
        );
        $statement->execute([$userId]);
        $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        foreach (array_slice($ids, max(0, $maximum - 1)) as $id) {
            $this->revoke($id);
        }
    }

    public function purgeExpired(): void
    {
        $this->pdo->exec(
            "UPDATE user_sessions SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP())
             WHERE expires_at<=UTC_TIMESTAMP()"
        );
    }
}
