<?php
declare(strict_types=1);
namespace Vigilancia\Controllers;

use Vigilancia\Http\JsonResponse;
use Vigilancia\Http\Request;
use Vigilancia\Middleware\CsrfMiddleware;
use Vigilancia\Services\AuthService;
use Vigilancia\Validation\Validator;

final class UserSecurityController
{
    public function __construct(private AuthService $auth) {}

    public function resetPassword(Request $request): void
    {
        CsrfMiddleware::verify($request->header('x-csrf-token'));
        $actor = $this->auth->current();
        $errors = Validator::required($request->body, ['user_id', 'new_password']);
        if ($errors) JsonResponse::error('Revisa los datos ingresados.', $errors, 422);
        $this->auth->resetPassword($actor, (int) $request->body['user_id'], (string) $request->body['new_password']);
        JsonResponse::success('Contraseña restablecida y sesiones revocadas.');
    }
}
