<?php
declare(strict_types=1);

namespace Vigilancia\Controllers;

use Vigilancia\Http\JsonResponse;
use Vigilancia\Http\Request;
use Vigilancia\Middleware\CsrfMiddleware;
use Vigilancia\Services\AuthService;
use Vigilancia\Services\UserAdminService;

final class UserAdminController
{
    public function __construct(private AuthService $auth, private UserAdminService $users)
    {
    }

    public function index(): void
    {
        JsonResponse::success('Usuarios administrativos consultados.', $this->users->index($this->auth->current()));
    }

    public function create(Request $request): void
    {
        CsrfMiddleware::verify($request->header('x-csrf-token'));
        $id = $this->users->create($this->auth->current(), $request->body);
        JsonResponse::success('Usuario creado correctamente.', ['id' => $id], 201);
    }

    public function update(Request $request): void
    {
        CsrfMiddleware::verify($request->header('x-csrf-token'));
        $this->users->update($this->auth->current(), (int) ($request->body['id'] ?? 0), $request->body);
        JsonResponse::success('Usuario actualizado correctamente.');
    }

    public function status(Request $request): void
    {
        CsrfMiddleware::verify($request->header('x-csrf-token'));
        $this->users->setActive($this->auth->current(), (int) ($request->body['id'] ?? 0), (bool) ($request->body['is_active'] ?? false));
        JsonResponse::success('Estado del usuario actualizado correctamente.');
    }
}
