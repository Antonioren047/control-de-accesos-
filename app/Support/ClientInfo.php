<?php
declare(strict_types=1);
namespace Vigilancia\Support;

final class ClientInfo
{
    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 45);
    }

    public static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'), 0, 500);
    }
}
