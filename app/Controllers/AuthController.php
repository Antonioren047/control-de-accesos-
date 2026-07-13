<?php
declare(strict_types=1);
namespace Vigilancia\Controllers;

use Vigilancia\Http\JsonResponse;
use Vigilancia\Http\Request;
use Vigilancia\Middleware\CsrfMiddleware;
use Vigilancia\Services\AuthService;
use Vigilancia\Validation\Validator;

final class AuthController
{
    public function __construct(private AuthService $auth) {}

    public function login(Request $request): void
    {
        CsrfMiddleware::verify($request->header('x-csrf-token') ?? ($request->body['_csrf'] ?? null));
        $errors = Validator::required($request->body, ['email', 'password']);
        if ($errors) JsonResponse::error('Revisa los datos ingresados.', $errors, 422);
        $user = $this->auth->login((string) $request->body['email'], (string) $request->body['password']);
        JsonResponse::success('Sesión iniciada correctamente.', ['user' => $user, 'csrf_token' => CsrfMiddleware::token()]);
    }

    public function logout(Request $request): void
    {
        CsrfMiddleware::verify($request->header('x-csrf-token'));
        $this->auth->current();
        $this->auth->logout();
        JsonResponse::success('Sesión cerrada correctamente.');
    }

    public function me(): void
    {
        $user = $this->auth->current();
        JsonResponse::success('Sesión activa.', [
            'user' => $this->auth->publicUser($user),
            'csrf_token' => CsrfMiddleware::token(),
        ]);
    }

    public function changePassword(Request $request): void
    {
        CsrfMiddleware::verify($request->header('x-csrf-token'));
        $user = $this->auth->current();
        $errors = Validator::required($request->body, ['current_password', 'new_password']);
        if ($errors) JsonResponse::error('Revisa los datos ingresados.', $errors, 422);
        $this->auth->changePassword($user, (string) $request->body['current_password'], (string) $request->body['new_password']);
        JsonResponse::success('Contraseña actualizada. Las demás sesiones fueron cerradas.');
    }

    public function updateTheme(Request $request): void
    {
        CsrfMiddleware::verify($request->header('x-csrf-token'));
        $user = $this->auth->current();
        $this->auth->updateTheme($user, (string) ($request->body['theme'] ?? ''));
        JsonResponse::success('Preferencia de tema guardada.');
    }
}
