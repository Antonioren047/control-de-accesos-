<?php
declare(strict_types=1);
namespace Vigilancia\Middleware;

use Vigilancia\Services\AuthorizationService;

final class PermissionMiddleware
{
    public function __construct(private AuthorizationService $authorization) {}
    public function handle(array $user, string $permission): void { $this->authorization->require($user, $permission); }
}
