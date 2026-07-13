<?php
declare(strict_types=1);
namespace Vigilancia\Auth;

final class PasswordPolicy
{
    public static function errors(string $password): array
    {
        $errors = [];
        if (strlen($password) < 12) $errors[] = 'Debe contener al menos 12 caracteres.';
        if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Debe contener una mayúscula.';
        if (!preg_match('/[a-z]/', $password)) $errors[] = 'Debe contener una minúscula.';
        if (!preg_match('/\d/', $password)) $errors[] = 'Debe contener un número.';
        if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'Debe contener un carácter especial.';
        return $errors;
    }

    public static function passes(string $password): bool
    {
        return self::errors($password) === [];
    }
}
