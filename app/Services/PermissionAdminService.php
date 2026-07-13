<?php
declare(strict_types=1);
namespace Vigilancia\Services;

use Vigilancia\Exceptions\HttpException;
use Vigilancia\Repositories\PermissionRepository;
use Vigilancia\Repositories\SecurityLogRepository;
use Vigilancia\Support\ClientInfo;

final class PermissionAdminService
{
    private AuthorizationService $authorization;

    public function __construct(
        private PermissionRepository $permissions,
        private SecurityLogRepository $logs
    ) {
        $this->authorization = new AuthorizationService($permissions);
    }

    public function matrix(array $actor): array
    {
        $this->authorization->require($actor, 'permissions.manage');
        $assignments = [];
        foreach ($this->permissions->roleAssignments() as $assignment) {
            $assignments[$assignment['role_code']][] = $assignment['permission_code'];
        }

        return [
            'roles' => array_map(static fn (array $role): array => [
                'code' => $role['code'],
                'name' => $role['name'],
                'description' => $role['description'],
                'permissions' => $assignments[$role['code']] ?? [],
            ], $this->permissions->roles()),
            'permissions' => array_map(static fn (array $permission): array => [
                'code' => $permission['code'],
                'module' => $permission['module'],
                'action' => $permission['action'],
                'name' => $permission['name'],
            ], $this->permissions->catalog()),
        ];
    }

    public function update(array $actor, string $roleCode, string $permissionCode, bool $allowed): void
    {
        $this->authorization->require($actor, 'permissions.manage');
        $roleId = $this->permissions->findRoleId($roleCode);
        $permissionId = $this->permissions->findPermissionId($permissionCode);
        if ($roleId === null) throw new HttpException('El rol indicado no existe.', 404);
        if ($permissionId === null) throw new HttpException('El permiso indicado no existe.', 404);
        if ($roleCode === 'superadmin' && !$allowed) {
            throw new HttpException('Los permisos base del Superadministrador no pueden desactivarse.', 422);
        }

        $this->permissions->setForRole($roleId, $permissionId, $allowed);
        $this->logs->record((int) $actor['id'], 'authorization.role_permission_updated', ClientInfo::ip(), ClientInfo::userAgent(), [
            'role' => $roleCode,
            'permission' => $permissionCode,
            'allowed' => $allowed,
        ]);
    }
}
