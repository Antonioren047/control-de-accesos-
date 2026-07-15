<?php
declare(strict_types=1);
namespace Vigilancia\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT u.*, r.code AS role_code, r.name AS role_name, c.name AS company_name
             FROM users u
             JOIN roles r ON r.id=u.role_id
             JOIN surveillance_companies c ON c.id=u.surveillance_company_id
             WHERE u.email=? LIMIT 1"
        );
        $statement->execute([$email]);
        return $statement->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT u.*, r.code AS role_code, r.name AS role_name, c.name AS company_name
             FROM users u
             JOIN roles r ON r.id=u.role_id
             JOIN surveillance_companies c ON c.id=u.surveillance_company_id
             WHERE u.id=? LIMIT 1"
        );
        $statement->execute([$id]);
        return $statement->fetch() ?: null;
    }

    public function registerSuccessfulLogin(int $id, ?string $newHash = null): void
    {
        $sql = "UPDATE users SET failed_attempts=0,locked_until=NULL,last_login_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP()";
        $parameters = [];
        if ($newHash !== null) {
            $sql .= ',password_hash=?';
            $parameters[] = $newHash;
        }
        $sql .= ' WHERE id=?';
        $parameters[] = $id;
        $this->pdo->prepare($sql)->execute($parameters);
    }

    public function registerFailedLogin(int $id, int $attempts, ?string $blockedUntil): void
    {
        $this->pdo->prepare(
            "UPDATE users SET failed_attempts=?,locked_until=?,updated_at=UTC_TIMESTAMP() WHERE id=?"
        )->execute([$attempts, $blockedUntil, $id]);
    }

    public function updatePassword(int $id, string $hash, bool $forceChange = false): void
    {
        $this->pdo->prepare(
            "UPDATE users SET password_hash=?,password_changed_at=UTC_TIMESTAMP(),
             force_password_change=?,failed_attempts=0,locked_until=NULL,updated_at=UTC_TIMESTAMP()
             WHERE id=?"
        )->execute([$hash, $forceChange ? 1 : 0, $id]);
    }

    public function updateTheme(int $id, string $theme): void
    {
        $this->pdo->prepare(
            "UPDATE users SET theme_preference=?,updated_at=UTC_TIMESTAMP() WHERE id=?"
        )->execute([$theme, $id]);
    }
}
