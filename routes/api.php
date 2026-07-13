<?php
declare(strict_types=1);

use Vigilancia\Controllers\AuthController;
use Vigilancia\Controllers\HealthController;
use Vigilancia\Controllers\UserSecurityController;
use Vigilancia\Database\Connection;
use Vigilancia\Http\JsonResponse;
use Vigilancia\Services\AuthService;
use Vigilancia\Support\Config;

$auth = new AuthService(Connection::make(Config::database()));
$authController = new AuthController($auth);
$userSecurityController = new UserSecurityController($auth);

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
$router->post('/users/password-reset', [$userSecurityController, 'resetPassword']);
