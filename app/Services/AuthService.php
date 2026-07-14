<?php
declare(strict_types=1);
namespace Vigilancia\Services;

use PDO;
use Vigilancia\Auth\BackoffPolicy;
use Vigilancia\Auth\PasswordPolicy;
use Vigilancia\Exceptions\HttpException;
use Vigilancia\Repositories\AuthAttemptRepository;
use Vigilancia\Repositories\PermissionRepository;
use Vigilancia\Repositories\SecurityLogRepository;
use Vigilancia\Repositories\SessionRepository;
use Vigilancia\Repositories\UserRepository;
use Vigilancia\Support\ClientInfo;

final class AuthService
{
    private UserRepository $users;
    private SessionRepository $sessions;
    private AuthAttemptRepository $attempts;
    private SecurityLogRepository $logs;
    private AuthorizationService $authorization;

    public function __construct(private PDO $pdo)
    {
        $this->users = new UserRepository($pdo);
        $this->sessions = new SessionRepository($pdo);
        $this->attempts = new AuthAttemptRepository($pdo);
        $this->logs = new SecurityLogRepository($pdo);
        $this->authorization = new AuthorizationService(new PermissionRepository($pdo));
    }

    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $identifierHash = hash('sha256', $email);
        $ip = ClientInfo::ip();
        $agent = ClientInfo::userAgent();
        $attempt = $this->attempts->find($identifierHash, $ip);
        if ($attempt && $attempt['blocked_until'] !== null && strtotime($attempt['blocked_until'] . ' UTC') > time()) {
            throw new HttpException('Demasiados intentos. Espera antes de volver a intentarlo.', 429);
        }

        $user = $this->users->findByEmail($email);
        $guardWebLoginAllowed = !$user || $user['role_code'] !== 'guard'
            || $this->settingInt('security.guard_web_login_enabled', 0) === 1;
        $valid = $user
            && (bool) $user['is_active']
            && $guardWebLoginAllowed
            && password_verify($password, $user['password_hash']);
        if (!$valid) {
            $failure = $this->attempts->registerFailure(
                $identifierHash,
                $ip,
                $this->settingInt('security.login_backoff_seconds', 60),
                $this->settingInt('security.login_max_backoff_seconds', 3600)
            );
            if ($user) {
                $globalAttempts = (int) $user['failed_attempts'] + 1;
                $globalSeconds = BackoffPolicy::seconds(
                    $globalAttempts,
                    $this->settingInt('security.login_backoff_seconds', 60),
                    $this->settingInt('security.login_max_backoff_seconds', 3600)
                );
                $globalBlockedUntil = $globalSeconds > 0 ? gmdate('Y-m-d H:i:s', time() + $globalSeconds) : null;
                $this->users->registerFailedLogin((int) $user['id'], $globalAttempts, $globalBlockedUntil);
                if ($globalSeconds > $failure['seconds']) $failure['seconds'] = $globalSeconds;
            }
            $this->logs->record($user ? (int) $user['id'] : null, 'auth.login_failed', $ip, $agent, [
                'identifier_hash' => $identifierHash,
                'attempts' => $failure['attempts'],
            ]);
            $status = $failure['seconds'] > 0 ? 429 : 401;
            throw new HttpException(
                $status === 429 ? 'Demasiados intentos. Espera antes de volver a intentarlo.' : 'Correo o contraseña incorrectos.',
                $status
            );
        }

        if ($user['locked_until'] !== null && strtotime($user['locked_until'] . ' UTC') > time()) {
            throw new HttpException('La cuenta está bloqueada temporalmente.', 429);
        }

        $newHash = password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)
            ? password_hash($password, PASSWORD_DEFAULT)
            : null;
        $this->attempts->clear($identifierHash, $ip);
        $this->users->registerSuccessfulLogin((int) $user['id'], $newHash);
        $this->sessions->purgeExpired();
        $limit = $user['role_code'] === 'guard' ? 1 : $this->settingInt('security.max_user_sessions', 5);
        $this->sessions->enforceLimit((int) $user['id'], max(1, $limit));

        $token = bin2hex(random_bytes(32));
        $hours = min(24, max(1, $this->settingInt('security.session_max_hours', 24)));
        $sessionId = $this->sessions->create((int) $user['id'], hash('sha256', $token), $ip, $agent, $hours);
        session_regenerate_id(true);
        $_SESSION['auth_user_id'] = (int) $user['id'];
        $_SESSION['auth_token'] = $token;
        $_SESSION['auth_session_id'] = $sessionId;

        $this->logs->record((int) $user['id'], 'auth.login_succeeded', $ip, $agent, ['session_id' => $sessionId]);
        $freshUser = $this->users->findById((int) $user['id']) ?? $user;
        return $this->publicUser($freshUser);
    }

    public function current(): array
    {
        $token = (string) ($_SESSION['auth_token'] ?? '');
        if ($token === '') throw new HttpException('Debes iniciar sesión.', 401);
        $user = $this->sessions->findActive(hash('sha256', $token));
        if (!$user || (int) ($user['id'] ?? 0) !== (int) ($_SESSION['auth_user_id'] ?? 0)) {
            $this->clearPhpSession();
            throw new HttpException('La sesión expiró o fue revocada.', 401);
        }
        $this->sessions->touch((int) $user['auth_session_id']);
        return $user;
    }

    public function currentOrNull(): ?array
    {
        try {
            return $this->current();
        } catch (HttpException) {
            return null;
        }
    }

    public function logout(): void
    {
        $userId = isset($_SESSION['auth_user_id']) ? (int) $_SESSION['auth_user_id'] : null;
        $sessionId = isset($_SESSION['auth_session_id']) ? (int) $_SESSION['auth_session_id'] : null;
        if ($sessionId) $this->sessions->revoke($sessionId);
        if ($userId) $this->logs->record($userId, 'auth.logout', ClientInfo::ip(), ClientInfo::userAgent());
        $this->clearPhpSession();
    }

    public function changePassword(array $user, string $currentPassword, string $newPassword): void
    {
        if ($user['role_code'] === 'resident') throw new HttpException('El residente no puede cambiar contraseÃ±as desde el portal.', 403);
        $this->authorization->require($user, 'auth.password.change');
        if (!password_verify($currentPassword, $user['password_hash'])) {
            throw new HttpException('La contraseña actual no es correcta.', 422, ['current_password' => ['No coincide.']]);
        }
        $errors = PasswordPolicy::errors($newPassword);
        if ($errors) throw new HttpException('La nueva contraseña no cumple la política.', 422, ['new_password' => $errors]);
        if (password_verify($newPassword, $user['password_hash'])) {
            throw new HttpException('La nueva contraseña debe ser diferente.', 422, ['new_password' => ['Debe ser diferente a la actual.']]);
        }
        $this->users->updatePassword((int) $user['id'], password_hash($newPassword, PASSWORD_DEFAULT));
        $this->sessions->revokeAll((int) $user['id'], (int) $user['auth_session_id']);
        $this->logs->record((int) $user['id'], 'auth.password_changed', ClientInfo::ip(), ClientInfo::userAgent());
    }

    public function resetPassword(array $actor, int $targetUserId, string $newPassword): void
    {
        $this->authorization->require($actor, 'users.password_reset');
        $errors = PasswordPolicy::errors($newPassword);
        if ($errors) throw new HttpException('La contraseña no cumple la política.', 422, ['new_password' => $errors]);
        $target = $this->users->findById($targetUserId);
        if (!$target) throw new HttpException('Usuario no encontrado.', 404);
        if ($actor['role_code'] !== 'superadmin'
            && (int) $actor['surveillance_company_id'] !== (int) $target['surveillance_company_id']) {
            throw new HttpException('No puedes administrar usuarios fuera de tu empresa.', 403);
        }
        $this->users->updatePassword($targetUserId, password_hash($newPassword, PASSWORD_DEFAULT), true);
        $this->sessions->revokeAll($targetUserId);
        $this->logs->record((int) $actor['id'], 'auth.password_reset', ClientInfo::ip(), ClientInfo::userAgent(), [
            'target_user_id' => $targetUserId,
        ]);
    }

    public function updateTheme(array $user, string $theme): void
    {
        $this->authorization->require($user, 'auth.profile.view');
        if (!in_array($theme, ['auto', 'light', 'dark'], true)) {
            throw new HttpException('Preferencia de tema inválida.', 422);
        }
        $this->users->updateTheme((int) $user['id'], $theme);
    }

    public function authorize(array $user, string $permission): void
    {
        $this->authorization->require($user, $permission);
    }

    public function sessions(array $user): array
    {
        if ($user['role_code'] === 'resident') throw new HttpException('El residente no puede consultar sesiones activas.', 403);
        $this->authorization->require($user, 'auth.sessions.view');
        $currentId = (int) ($user['auth_session_id'] ?? 0);

        return array_map(static fn (array $session): array => [
            'id' => (int) $session['id'],
            'ip_address' => $session['ip_address'],
            'user_agent' => $session['user_agent'],
            'last_activity_at' => $session['last_activity_at'],
            'expires_at' => $session['expires_at'],
            'created_at' => $session['created_at'],
            'is_current' => (int) $session['id'] === $currentId,
        ], $this->sessions->activeForUser((int) $user['id']));
    }

    public function revokeSession(array $user, int $sessionId): void
    {
        if ($user['role_code'] === 'resident') throw new HttpException('El residente no puede revocar sesiones.', 403);
        $this->authorization->require($user, 'auth.sessions.revoke');
        if ($sessionId <= 0) throw new HttpException('La sesión indicada no es válida.', 422);
        if ($sessionId === (int) ($user['auth_session_id'] ?? 0)) {
            throw new HttpException('Usa la opción Salir para cerrar esta sesión.', 422);
        }
        if (!$this->sessions->revokeOwned($sessionId, (int) $user['id'])) {
            throw new HttpException('La sesión no existe o ya fue revocada.', 404);
        }
        $this->logs->record((int) $user['id'], 'auth.session_revoked', ClientInfo::ip(), ClientInfo::userAgent(), [
            'session_id' => $sessionId,
        ]);
    }

    public function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => ['code' => $user['role_code'], 'name' => $user['role_name']],
            'company' => ['id' => (int) $user['surveillance_company_id'], 'name' => $user['company_name']],
            'theme' => $user['theme_preference'] ?? 'auto',
            'is_active' => (bool) ($user['is_active'] ?? false),
            'last_login_at' => $user['last_login_at'] ?? null,
            'password_changed_at' => $user['password_changed_at'] ?? null,
            'created_at' => $user['created_at'] ?? null,
            'force_password_change' => (bool) ($user['force_password_change'] ?? false),
            'permissions' => $this->authorization->permissionsFor($user),
        ];
    }

    private function settingInt(string $key, int $default): int
    {
        $statement = $this->pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key=? LIMIT 1');
        $statement->execute([$key]);
        $value = $statement->fetchColumn();
        return $value === false ? $default : (int) $value;
    }

    private function clearPhpSession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }
}
