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
}
