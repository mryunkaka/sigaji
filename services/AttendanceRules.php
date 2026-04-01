<?php

final class AttendanceRules
{
    public static function calculateLateFromRecord(?string $status, ?string $jamMasuk, ?int $shift, ?string $jabatan, ?int $toleranceMinutes = 0): int
    {
        if (strtolower(trim((string) $status)) !== 'hadir') {
            return 0;
        }

        return self::calculateLateMinutes($jamMasuk, $shift, $jabatan, $toleranceMinutes);
    }

    public static function calculateLateMinutes(?string $jamMasuk, ?int $shift, ?string $jabatan, ?int $toleranceMinutes = 0): int
    {
        if (!$jamMasuk || !$shift) {
            return 0;
        }

        $parsedTime = self::parseHourMinute($jamMasuk);
        if ($parsedTime === null) {
            return 0;
        }

        [$hour, $minute] = $parsedTime;
        $arrival = ($hour * 60) + $minute;
        $role = strtolower(trim((string) $jabatan));
        $tolerance = max(0, (int) $toleranceMinutes);

        if ($role === 'security') {
            $target = match ($shift) {
                1 => 8 * 60,
                2 => 16 * 60,
                3 => (23 * 60) + 59,
                default => null,
            };

            if ($target === null) {
                return 0;
            }

            if ($shift === 3) {
                if ($hour < 23 || ($hour === 23 && $minute < 59)) {
                    return 0;
                }

                if ($hour < 12) {
                    $arrival += 1440;
                }
            }

            return max(0, $arrival - ($target + $tolerance));
        }

        if ($role === 'general') {
            if ($shift === 1) {
                return max(0, $arrival - ((7 * 60) + $tolerance));
            }

            if ($shift === 2) {
                if ($arrival < (7 * 60)) {
                    $arrival += 1440;
                }

                return max(0, $arrival - ((19 * 60) + $tolerance));
            }

            return 0;
        }

        $target = match ($shift) {
            1 => 7 * 60,
            2 => 15 * 60,
            3 => 23 * 60,
            default => null,
        };

        if ($target === null) {
            return 0;
        }

        if ($shift === 3 && $arrival < (7 * 60)) {
            $arrival += 1440;
        }

        return max(0, $arrival - ($target + $tolerance));
    }

    private static function parseHourMinute(string $jamMasuk): ?array
    {
        $time = substr(trim($jamMasuk), 0, 5);
        if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2]];
    }
}
