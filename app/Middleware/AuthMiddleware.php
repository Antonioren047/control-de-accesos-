<?php
declare(strict_types=1);
namespace Vigilancia\Middleware;

use Vigilancia\Services\AuthService;

final class AuthMiddleware
{
    public function __construct(private AuthService $auth) {}
    public function handle(): array { return $this->auth->current(); }
}
