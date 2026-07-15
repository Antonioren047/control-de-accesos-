<?php
declare(strict_types=1);
namespace Vigilancia\Services;

use Vigilancia\Exceptions\HttpException;
use Vigilancia\Repositories\PermissionRepository;

final class AuthorizationService
{
    public function __construct(private PermissionRepository $permissions) {}

    public function permissionsFor(array $user): array
    {
        return $this->permissions->forUser((int) $user['id'], (int) $user['role_id']);
    }

    public function require(array $user, string $permission): void
    {
        if (!in_array($permission, $this->permissionsFor($user), true)) {
            throw new HttpException('No cuentas con permiso para realizar esta acción.', 403);
        }
    }
}
