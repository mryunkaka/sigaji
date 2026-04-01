<?php

final class ShiftService
{
    private static array $ruleCache = [];

    public static function getGlobalRules(int $unitId): array
    {
        return self::getRules($unitId, 'global');
    }

    public static function getJabatanRules(int $unitId, ?string $jabatan): array
    {
        $jabatanKey = trim((string) $jabatan);
        if ($jabatanKey === '') {
            return [];
        }

        return self::getRules($unitId, 'jabatan', $jabatanKey);
    }

    public static function getShiftOptions(int $unitId): array
    {
        $rules = self::getGlobalRules($unitId);
        if ($rules === []) {
            $options = [];
            for ($shift = 1; $shift <= 3; $shift++) {
                $options[(string) $shift] = 'Shift ' . $shift;
            }
            return $options;
        }

        $options = [];
        foreach ($rules as $shiftCode => $rule) {
            $label = 'Shift ' . $shiftCode;
            $jamMasuk = trim((string) ($rule['jam_masuk'] ?? ''));
            $jamKeluar = trim((string) ($rule['jam_keluar'] ?? ''));
            if ($jamMasuk !== '' || $jamKeluar !== '') {
                $label .= ' (' . substr($jamMasuk ?: '--:--', 0, 5) . ' - ' . substr($jamKeluar ?: '--:--', 0, 5) . ')';
            }
            $options[(string) $shiftCode] = $label;
        }

        return $options;
    }

    public static function resolveEmployeeShiftContext(array $employee, ?int $preferredShift = null): array
    {
        $unitId = (int) ($employee['unit_id'] ?? 0);
        $globalRules = self::getGlobalRules($unitId);
        $jabatanRules = self::getJabatanRules($unitId, (string) ($employee['jabatan'] ?? ''));
        $effectiveRules = $jabatanRules !== [] ? $jabatanRules : $globalRules;

        $defaultShift = self::firstAvailableShift($effectiveRules);
        $userDefaultShift = (int) ($employee['default_shift'] ?? 0);
        if ($userDefaultShift > 0) {
            $defaultShift = $userDefaultShift;
        }

        $shift = $preferredShift ?: $defaultShift;
        $rule = $effectiveRules[$shift] ?? $globalRules[$shift] ?? null;

        $scheduledJamMasuk = self::normalizeTimeOrNull($employee['jam_masuk_default'] ?? null);
        if ($scheduledJamMasuk === null) {
            $scheduledJamMasuk = self::normalizeTimeOrNull($rule['jam_masuk'] ?? null);
        }

        $scheduledJamKeluar = self::normalizeTimeOrNull($employee['jam_keluar_default'] ?? null);
        if ($scheduledJamKeluar === null) {
            $scheduledJamKeluar = self::normalizeTimeOrNull($rule['jam_keluar'] ?? null);
        }

        return [
            'default_shift' => $defaultShift > 0 ? $defaultShift : null,
            'effective_shift' => $shift > 0 ? $shift : null,
            'scheduled_jam_masuk' => $scheduledJamMasuk,
            'scheduled_jam_keluar' => $scheduledJamKeluar,
            'rules' => self::normalizeRulesForClient($effectiveRules !== [] ? $effectiveRules : $globalRules),
        ];
    }

    public static function getJabatanRuleRows(int $unitId): array
    {
        try {
            return fetch_all(
                'SELECT id, unit_id, jabatan, shift_code, jam_masuk, jam_keluar
                 FROM shift_rules
                 WHERE unit_id = :unit_id AND scope_type = :scope_type
                 ORDER BY jabatan ASC, shift_code ASC',
                ['unit_id' => $unitId, 'scope_type' => 'jabatan']
            );
        } catch (Throwable $exception) {
            return [];
        }
    }

    public static function replaceJabatanRules(int $unitId, array $rows): void
    {
        execute_query(
            'DELETE FROM shift_rules
             WHERE unit_id = :unit_id AND scope_type = :scope_type',
            ['unit_id' => $unitId, 'scope_type' => 'jabatan']
        );

        foreach ($rows as $row) {
            execute_query(
                'INSERT INTO shift_rules (unit_id, scope_type, jabatan, shift_code, jam_masuk, jam_keluar, created_at, updated_at)
                 VALUES (:unit_id, :scope_type, :jabatan, :shift_code, :jam_masuk, :jam_keluar, :created_at, :updated_at)',
                [
                    'unit_id' => $unitId,
                    'scope_type' => 'jabatan',
                    'jabatan' => $row['jabatan'],
                    'shift_code' => $row['shift_code'],
                    'jam_masuk' => $row['jam_masuk'],
                    'jam_keluar' => $row['jam_keluar'],
                    'created_at' => now_string(),
                    'updated_at' => now_string(),
                ]
            );
        }

        self::$ruleCache = [];
    }

    public static function replaceGlobalRules(int $unitId, array $rows): void
    {
        execute_query(
            'DELETE FROM shift_rules
             WHERE unit_id = :unit_id AND scope_type = :scope_type',
            ['unit_id' => $unitId, 'scope_type' => 'global']
        );

        foreach ($rows as $row) {
            execute_query(
                'INSERT INTO shift_rules (unit_id, scope_type, jabatan, shift_code, jam_masuk, jam_keluar, created_at, updated_at)
                 VALUES (:unit_id, :scope_type, :jabatan, :shift_code, :jam_masuk, :jam_keluar, :created_at, :updated_at)',
                [
                    'unit_id' => $unitId,
                    'scope_type' => 'global',
                    'jabatan' => '',
                    'shift_code' => $row['shift_code'],
                    'jam_masuk' => $row['jam_masuk'],
                    'jam_keluar' => $row['jam_keluar'],
                    'created_at' => now_string(),
                    'updated_at' => now_string(),
                ]
            );
        }

        self::$ruleCache = [];
    }

    private static function getRules(int $unitId, string $scopeType, ?string $jabatan = null): array
    {
        $cacheKey = $unitId . '|' . $scopeType . '|' . strtolower(trim((string) $jabatan));
        if (isset(self::$ruleCache[$cacheKey])) {
            return self::$ruleCache[$cacheKey];
        }

        try {
            $params = ['unit_id' => $unitId, 'scope_type' => $scopeType];
            $sql = 'SELECT shift_code, jam_masuk, jam_keluar
                    FROM shift_rules
                    WHERE unit_id = :unit_id AND scope_type = :scope_type';

            if ($scopeType === 'jabatan') {
                $sql .= ' AND jabatan = :jabatan';
                $params['jabatan'] = trim((string) $jabatan);
            }

            $sql .= ' ORDER BY shift_code ASC';
            $rows = fetch_all($sql, $params);
        } catch (Throwable $exception) {
            $rows = [];
        }

        $rules = [];
        foreach ($rows as $row) {
            $shiftCode = (int) ($row['shift_code'] ?? 0);
            if ($shiftCode <= 0) {
                continue;
            }
            $rules[$shiftCode] = [
                'jam_masuk' => self::normalizeTimeOrNull($row['jam_masuk'] ?? null),
                'jam_keluar' => self::normalizeTimeOrNull($row['jam_keluar'] ?? null),
            ];
        }

        self::$ruleCache[$cacheKey] = $rules;
        return $rules;
    }

    private static function firstAvailableShift(array $rules): ?int
    {
        $keys = array_keys($rules);
        if ($keys === []) {
            return 1;
        }

        sort($keys, SORT_NUMERIC);
        return (int) $keys[0];
    }

    private static function normalizeTimeOrNull($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return strlen($value) >= 5 ? substr($value, 0, 5) : null;
    }

    private static function normalizeRulesForClient(array $rules): array
    {
        $normalized = [];
        foreach ($rules as $shiftCode => $rule) {
            $normalized[(string) $shiftCode] = [
                'jam_masuk' => self::normalizeTimeOrNull($rule['jam_masuk'] ?? null),
                'jam_keluar' => self::normalizeTimeOrNull($rule['jam_keluar'] ?? null),
            ];
        }

        return $normalized;
    }
}
