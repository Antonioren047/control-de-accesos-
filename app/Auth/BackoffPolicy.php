<?php
declare(strict_types=1);
namespace Vigilancia\Auth;

final class BackoffPolicy
{
    public static function seconds(int $failedAttempts, int $base = 60, int $maximum = 3600): int
    {
        if ($failedAttempts < 5) return 0;
        $exponent = min(10, $failedAttempts - 5);
        return min($maximum, $base * (2 ** $exponent));
    }
}
