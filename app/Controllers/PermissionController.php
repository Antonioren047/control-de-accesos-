<?php
declare(strict_types=1);
namespace Vigilancia\Controllers;

use Vigilancia\Http\JsonResponse;
use Vigilancia\Http\Request;
use Vigilancia\Middleware\CsrfMiddleware;
use Vigilancia\Services\AuthService;
use Vigilancia\Services\PermissionAdminService;
use Vigilancia\Validation\Validator;

final class PermissionController
{
    public function __construct(
        private AuthService $auth,
        private PermissionAdminService $permissions
    ) {}

    public function matrix(): void
    {
        JsonResponse::success('Matriz de permisos.', $this->permissions->matrix($this->auth->current()));
    }

    public function update(Request $request): void
    {
        CsrfMiddleware::verify($request->header('x-csrf-token'));
        $errors = Validator::required($request->body, ['role_code', 'permission_code', 'allowed']);
        if ($errors) JsonResponse::error('Revisa los datos ingresados.', $errors, 422);
        $allowed = filter_var($request->body['allowed'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($allowed === null) JsonResponse::error('El estado del permiso no es válido.', [], 422);
        $this->permissions->update(
            $this->auth->current(),
            (string) $request->body['role_code'],
            (string) $request->body['permission_code'],
            $allowed
        );
        JsonResponse::success('Permiso actualizado correctamente.');
    }
}
