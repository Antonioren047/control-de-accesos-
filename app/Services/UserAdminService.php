<?php
declare(strict_types=1);

namespace Vigilancia\Services;

use PDO;
use PDOException;
use Vigilancia\Auth\AuthorizationService;
use Vigilancia\Auth\PasswordPolicy;
use Vigilancia\Exceptions\HttpException;
use Vigilancia\Repositories\PermissionRepository;
use Vigilancia\Repositories\SecurityLogRepository;
use Vigilancia\Repositories\UserAdminRepository;
use Vigilancia\Support\ClientInfo;
use Vigilancia\Validation\Validator;

final class UserAdminService
{
    private AuthorizationService $authorization;

    public function __construct(
        private PDO $pdo,
        private UserAdminRepository $repository,
        private SecurityLogRepository $logs
    ) {
        $this->authorization = new AuthorizationService(new PermissionRepository($pdo));
    }

    public function index(array $actor): array
    {
        $this->requireGlobal($actor);
        $companyId = (int) $actor['surveillance_company_id'];
        return [
            'items' => $this->repository->administrativeUsers($companyId),
            'catalog' => $this->repository->catalog($companyId),
        ];
    }

    public function create(array $actor, array $input): int
    {
        $this->requireGlobal($actor);
        $data = $this->validated($actor, $input, true);
        try {
            $this->pdo->beginTransaction();
            $id = $this->repository->create($data, (int) $actor['surveillance_company_id']);
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ((string) $e->getCode() === '23000') throw new HttpException('El correo ya pertenece a otro usuario.', 409);
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        $this->audit($actor, 'users.administrative_created', ['user_id' => $id, 'role_code' => $data['role_code']]);
        return $id;
    }

    public function update(array $actor, int $id, array $input): void
    {
        $this->requireGlobal($actor);
        $target = $id > 0 ? $this->repository->findAdministrative($id, (int) $actor['surveillance_company_id']) : null;
        if (!$target) {
            throw new HttpException('El usuario administrativo no existe.', 404);
        }
        if ($id === (int) $actor['id']) throw new HttpException('No puedes cambiar tu propio rol desde este formulario.', 409);
        $data = $this->validated($actor, $input, false);
        if ($target['role_code'] === 'superadmin' && $data['role_code'] !== 'superadmin'
            && $this->repository->activeGlobalCount((int) $actor['surveillance_company_id']) <= 1) {
            throw new HttpException('Debe permanecer al menos un usuario global activo.', 409);
        }
        try {
            $this->pdo->beginTransaction();
            $this->repository->update($id, $data);
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ((string) $e->getCode() === '23000') throw new HttpException('El correo ya pertenece a otro usuario.', 409);
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        $this->audit($actor, 'users.administrative_updated', ['user_id' => $id, 'role_code' => $data['role_code']]);
    }

    public function setActive(array $actor, int $id, bool $active): void
    {
        $this->requireGlobal($actor);
        $target = $this->repository->findAdministrative($id, (int) $actor['surveillance_company_id']);
        if (!$target) throw new HttpException('El usuario administrativo no existe.', 404);
        if ($id === (int) $actor['id']) throw new HttpException('No puedes desactivar tu propia cuenta.', 409);
        if (!$active && $target['role_code'] === 'superadmin'
            && $this->repository->activeGlobalCount((int) $actor['surveillance_company_id']) <= 1) {
            throw new HttpException('Debe permanecer al menos un usuario global activo.', 409);
        }
        $this->repository->setActive($id, $active);
        $this->audit($actor, 'users.administrative_status_changed', ['user_id' => $id, 'is_active' => $active]);
    }

    private function validated(array $actor, array $input, bool $creating): array
    {
        $required = ['full_name', 'email', 'role_code'];
        if ($creating) $required[] = 'password';
        $errors = Validator::required($input, $required);
        if ($errors) throw new HttpException('Completa los datos obligatorios del usuario.', 422, $errors);
        $email = strtolower(trim((string) $input['email']));
        if (!Validator::email($email)) throw new HttpException('El correo no es válido.', 422);
        $role = (string) $input['role_code'];
        if (!in_array($role, ['superadmin', 'admin', 'supervisor'], true)) {
            throw new HttpException('Para vigilantes y residentes utiliza su formulario especializado.', 422);
        }
        $password = (string) ($input['password'] ?? '');
        if ($password !== '' && ($passwordErrors = PasswordPolicy::errors($password))) {
            throw new HttpException('La contraseña no cumple la política.', 422, ['password' => $passwordErrors]);
        }
        $clientIds = $role === 'superadmin' ? [] : $this->ids($input['client_ids'] ?? []);
        $locationIds = $role === 'superadmin' ? [] : $this->ids($input['location_ids'] ?? []);
        if (!$this->repository->scopesBelongToCompany($clientIds, $locationIds, (int) $actor['surveillance_company_id'])) {
            throw new HttpException('Uno de los alcances seleccionados no pertenece a la empresa.', 403);
        }
        return [
            'full_name' => trim((string) $input['full_name']),
            'email' => $email,
            'role_code' => $role,
            'password_hash' => $password === '' ? null : password_hash($password, PASSWORD_DEFAULT),
            'client_ids' => $clientIds,
            'location_ids' => $locationIds,
            'actor_id' => (int) $actor['id'],
        ];
    }

    private function requireGlobal(array $actor): void
    {
        $this->authorization->require($actor, 'users.manage');
        if ($actor['role_code'] !== 'superadmin') throw new HttpException('Solo el usuario global puede administrar todos los tipos de usuario.', 403);
    }

    private function ids(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);
        return array_values(array_unique(array_filter(array_map('intval', $items), static fn (int $id): bool => $id > 0)));
    }

    private function audit(array $actor, string $event, array $context): void
    {
        $this->logs->record((int) $actor['id'], $event, ClientInfo::ip(), ClientInfo::userAgent(), $context);
    }
}
