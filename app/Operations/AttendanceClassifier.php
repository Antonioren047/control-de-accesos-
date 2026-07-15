<?php
declare(strict_types=1);
namespace Vigilancia\Operations;

use DateTimeImmutable;

final class AttendanceClassifier
{
    public static function entry(DateTimeImmutable $now, DateTimeImmutable $start, DateTimeImmutable $end, int $tolerance): array
    {
        $late = max(0, (int) floor(($now->getTimestamp() - $start->getTimestamp()) / 60));
        if ($now < $start->modify('-' . $tolerance . ' minutes') || $now > $end) return ['classification'=>'outside_schedule','minutes_late'=>0];
        if ($now > $start->modify('+' . $tolerance . ' minutes')) return ['classification'=>'late','minutes_late'=>$late];
        return ['classification'=>'on_time','minutes_late'=>0];
    }

    public static function exit(DateTimeImmutable $now, DateTimeImmutable $end, int $earlyTolerance, int $overtimeAfter): array
    {
        if ($now < $end->modify('-' . $earlyTolerance . ' minutes')) return ['classification'=>'early_departure','minutes_early'=>(int)ceil(($end->getTimestamp()-$now->getTimestamp())/60),'overtime_minutes'=>0];
        if ($now > $end->modify('+' . $overtimeAfter . ' minutes')) return ['classification'=>'overtime','minutes_early'=>0,'overtime_minutes'=>(int)floor(($now->getTimestamp()-$end->getTimestamp())/60)];
        return ['classification'=>'completed','minutes_early'=>0,'overtime_minutes'=>0];
    }
}
