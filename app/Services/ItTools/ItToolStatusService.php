<?php

namespace App\Services\ItTools;

class ItToolStatusService
{
    public static function level(?int $days): string
    {
        if ($days === null) {
            return 'unknown';
        }

        return match (true) {
            $days < 0 => 'expired',
            $days <= 7 => 'critical',
            $days <= 30 => 'warning',
            $days <= 60 => 'notice',
            default => 'ok',
        };
    }

    public static function summary(?int $days, string $type): array
    {
        $level = self::level($days);

        return [
            'type' => $type,
            'days_remaining' => $days,
            'level' => $level,
            'action_required' => in_array($level, ['expired', 'critical', 'warning'], true),
        ];
    }
}
