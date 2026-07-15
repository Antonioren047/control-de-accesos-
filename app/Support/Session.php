<?php
declare(strict_types=1); namespace Vigilancia\Support;
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $minutes = min(1440, max(1, (int) Env::get('SESSION_LIFETIME', 1440)));
        session_name((string) Env::get('SESSION_NAME', 'vigilancia_session'));
        session_set_cookie_params([
            'lifetime' => $minutes * 60,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $sessionPath = dirname(__DIR__, 2) . '/storage/cache/sessions';
        if (!is_dir($sessionPath)) @mkdir($sessionPath, 0770, true);
        if (is_dir($sessionPath) && is_writable($sessionPath)) {
            session_save_path($sessionPath);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) ($minutes * 60));
        session_start();
    }
}
