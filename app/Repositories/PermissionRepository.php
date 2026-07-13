<?php
declare(strict_types=1);
namespace Vigilancia\Repositories;

use PDO;

final class PermissionRepository
{
    public function __construct(private PDO $pdo) {}

    public function forUser(int $userId, int $roleId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT p.code FROM role_permissions rp
             JOIN permissions p ON p.id=rp.permission_id
             WHERE rp.role_id=?"
        );
        $statement->execute([$roleId]);
        $permissions = array_fill_keys($statement->fetchAll(PDO::FETCH_COLUMN), true);

        $overrides = $this->pdo->prepare(
            "SELECT p.code,up.is_allowed FROM user_permissions up
             JOIN permissions p ON p.id=up.permission_id WHERE up.user_id=?"
        );
        $overrides->execute([$userId]);
        foreach ($overrides->fetchAll() as $override) {
            if ((bool) $override['is_allowed']) $permissions[$override['code']] = true;
            else unset($permissions[$override['code']]);
        }
        $result = array_keys($permissions);
        sort($result);
        return $result;
    }

    public function catalog(): array
    {
        return $this->pdo->query(
            "SELECT id,code,module,action,name FROM permissions ORDER BY module,name"
        )->fetchAll();
    }

    public function roles(): array
    {
        return $this->pdo->query(
            "SELECT id,code,name,description FROM roles WHERE is_active=1 ORDER BY id"
        )->fetchAll();
    }

    public function roleAssignments(): array
    {
        return $this->pdo->query(
            "SELECT r.code AS role_code,p.code AS permission_code
             FROM role_permissions rp
             JOIN roles r ON r.id=rp.role_id
             JOIN permissions p ON p.id=rp.permission_id
             ORDER BY r.id,p.code"
        )->fetchAll();
    }

    public function findRoleId(string $code): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM roles WHERE code=? AND is_active=1 LIMIT 1');
        $statement->execute([$code]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function findPermissionId(string $code): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM permissions WHERE code=? LIMIT 1');
        $statement->execute([$code]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function setForRole(int $roleId, int $permissionId, bool $allowed): void
    {
        if ($allowed) {
            $this->pdo->prepare(
                'INSERT IGNORE INTO role_permissions(role_id,permission_id,created_at) VALUES(?,?,UTC_TIMESTAMP())'
            )->execute([$roleId, $permissionId]);
            return;
        }
        $this->pdo->prepare(
            'DELETE FROM role_permissions WHERE role_id=? AND permission_id=?'
        )->execute([$roleId, $permissionId]);
    }
}
