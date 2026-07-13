<?php
declare(strict_types=1);

use Vigilancia\Controllers\AuthController;
use Vigilancia\Controllers\HealthController;
use Vigilancia\Controllers\PermissionController;
use Vigilancia\Controllers\UserSecurityController;
use Vigilancia\Database\Connection;
use Vigilancia\Http\JsonResponse;
use Vigilancia\Services\AuthService;
use Vigilancia\Services\PermissionAdminService;
use Vigilancia\Repositories\PermissionRepository;
use Vigilancia\Repositories\SecurityLogRepository;
use Vigilancia\Support\Config;

$pdo = Connection::make(Config::database());
$auth = new AuthService($pdo);
$authController = new AuthController($auth);
$userSecurityController = new UserSecurityController($auth);
$permissionController = new PermissionController(
    $auth,
    new PermissionAdminService(new PermissionRepository($pdo), new SecurityLogRepository($pdo))
);

$router->get('/health', new HealthController());
$router->get('/', static fn () => JsonResponse::success('API de Control de Accesos', [
    'version' => '2.0.0',
    'documentation' => '../docs/',
]));
$router->post('/auth/login', [$authController, 'login']);
$router->post('/auth/logout', [$authController, 'logout']);
$router->get('/auth/me', [$authController, 'me']);
$router->post('/auth/password', [$authController, 'changePassword']);
$router->post('/auth/theme', [$authController, 'updateTheme']);
$router->get('/auth/sessions', [$authController, 'sessions']);
$router->post('/auth/sessions/revoke', [$authController, 'revokeSession']);
$router->post('/users/password-reset', [$userSecurityController, 'resetPassword']);
$router->get('/authorization/roles', [$permissionController, 'matrix']);
$router->post('/authorization/roles', [$permissionController, 'update']);
