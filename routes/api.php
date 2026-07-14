<?php
declare(strict_types=1);

use Vigilancia\Controllers\AuthController;
use Vigilancia\Controllers\HealthController;
use Vigilancia\Controllers\PermissionController;
use Vigilancia\Controllers\OrganizationController;
use Vigilancia\Controllers\UserSecurityController;
use Vigilancia\Controllers\WorkforceController;
use Vigilancia\Controllers\OperationalController;
use Vigilancia\Controllers\OfflineController;
use Vigilancia\Controllers\AccessController;
use Vigilancia\Database\Connection;
use Vigilancia\Http\JsonResponse;
use Vigilancia\Services\AuthService;
use Vigilancia\Services\PermissionAdminService;
use Vigilancia\Services\OrganizationService;
use Vigilancia\Services\CredentialAssetService;
use Vigilancia\Services\WorkforceService;
use Vigilancia\Repositories\OrganizationRepository;
use Vigilancia\Repositories\PermissionRepository;
use Vigilancia\Repositories\SecurityLogRepository;
use Vigilancia\Repositories\WorkforceRepository;
use Vigilancia\Repositories\OperationalRepository;
use Vigilancia\Repositories\OfflineRepository;
use Vigilancia\Services\OperationalService;
use Vigilancia\Services\OperationalPhotoService;
use Vigilancia\Services\OfflineService;
use Vigilancia\Services\OfflineEvidenceService;
use Vigilancia\Repositories\AccessRepository;
use Vigilancia\Services\AccessService;
use Vigilancia\Services\AccessAssetService;
use Vigilancia\Support\Config;

$pdo = Connection::make(Config::database());
$auth = new AuthService($pdo);
$authController = new AuthController($auth);
$userSecurityController = new UserSecurityController($auth);
$permissionController = new PermissionController(
    $auth,
    new PermissionAdminService(new PermissionRepository($pdo), new SecurityLogRepository($pdo))
);
$organizationController = new OrganizationController(
    $auth,
    new OrganizationService($pdo, new OrganizationRepository($pdo), new SecurityLogRepository($pdo))
);
$workforceController = new WorkforceController(
    $auth,
    new WorkforceService($pdo, new WorkforceRepository($pdo), new CredentialAssetService($root), new SecurityLogRepository($pdo))
);
$operationalController = new OperationalController(
    $auth,
    new OperationalService($pdo, new OperationalRepository($pdo), new OperationalPhotoService($root), new SecurityLogRepository($pdo), new OfflineRepository($pdo))
);
$offlineController = new OfflineController($auth,new OfflineService($pdo,new OfflineRepository($pdo),new OfflineEvidenceService($root),new SecurityLogRepository($pdo),new OperationalRepository($pdo)));
$accessController = new AccessController($auth,new AccessService($pdo,new AccessRepository($pdo),new OperationalRepository($pdo),new AccessAssetService($root),new SecurityLogRepository($pdo)));

$router->get('/health', new HealthController());
$router->get('/', static fn () => JsonResponse::success('API de Control de Accesos', [
    'version' => '7.0.0',
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
$router->get('/organization/clients', [$organizationController, 'clients']);
$router->get('/organization/locations', [$organizationController, 'locations']);
$router->get('/organization/access-points', [$organizationController, 'accessPoints']);
$router->get('/organization/units', [$organizationController, 'units']);
$router->get('/organization/residents', [$organizationController, 'residents']);
$router->post('/organization/create', [$organizationController, 'create']);
$router->post('/organization/status', [$organizationController, 'status']);
$router->get('/workforce/guards', [$workforceController, 'guards']);
$router->get('/workforce/shifts', [$workforceController, 'shifts']);
$router->get('/workforce/assignments', [$workforceController, 'assignments']);
$router->post('/workforce/create', [$workforceController, 'create']);
$router->post('/workforce/action', [$workforceController, 'action']);
$router->get('/operations/catalog', [$operationalController, 'catalog']);
$router->post('/operations/start', [$operationalController, 'start']);
$router->get('/operations/current', [$operationalController, 'current']);
$router->post('/operations/close', [$operationalController, 'close']);
$router->get('/operations/sessions', [$operationalController, 'sessions']);
$router->get('/operations/attendance', [$operationalController, 'attendance']);
$router->post('/operations/manual-close', [$operationalController, 'manualClose']);
$router->post('/offline/sync', [$offlineController, 'sync']);
$router->get('/offline/conflicts', [$offlineController, 'conflicts']);
$router->post('/offline/review', [$offlineController, 'review']);
$router->get('/access/catalog', [$accessController, 'catalog']);
$router->get('/visits', [$accessController, 'visits']);
$router->post('/visits', [$accessController, 'createVisit']);
$router->post('/visits/action', [$accessController, 'visitAction']);
$router->get('/visits/validate', [$accessController, 'validateVisit']);
$router->post('/visits/check-in', [$accessController, 'checkInVisit']);
$router->post('/visits/check-out', [$accessController, 'checkOutVisit']);
$router->get('/access/active', [$accessController, 'active']);
$router->get('/providers', [$accessController, 'providers']);
$router->post('/providers', [$accessController, 'createProvider']);
$router->get('/providers/validate', [$accessController, 'validateProvider']);
$router->post('/providers/check-in', [$accessController, 'checkInProvider']);
$router->post('/providers/check-out', [$accessController, 'checkOutProvider']);
