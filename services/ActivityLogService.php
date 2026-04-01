<?php

final class ActivityLogService
{
    public static function log(
        string $action,
        string $description,
        array $context = [],
        ?int $userId = null,
        ?int $unitId = null,
        string $targetType = '',
        ?string $targetId = null
    ): void {
        try {
            execute_query(
                'INSERT INTO activity_logs (
                    user_id,
                    unit_id,
                    session_token,
                    action,
                    target_type,
                    target_id,
                    description,
                    context_json,
                    ip_address,
                    user_agent,
                    occurred_at,
                    created_at,
                    updated_at
                ) VALUES (
                    :user_id,
                    :unit_id,
                    :session_token,
                    :action,
                    :target_type,
                    :target_id,
                    :description,
                    :context_json,
                    :ip_address,
                    :user_agent,
                    :occurred_at,
                    :created_at,
                    :updated_at
                )',
                [
                    'user_id' => $userId,
                    'unit_id' => $unitId,
                    'session_token' => self::sessionToken(),
                    'action' => $action,
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                    'description' => $description,
                    'context_json' => $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'ip_address' => self::ipAddress(),
                    'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    'occurred_at' => now_string(),
                    'created_at' => now_string(),
                    'updated_at' => now_string(),
                ]
            );
        } catch (Throwable $exception) {
            // Activity log must never block the main flow.
        }
    }

    public static function logCurrentUser(
        string $action,
        string $description,
        array $context = [],
        string $targetType = '',
        $targetId = null
    ): void {
        $user = Auth::user();
        self::log(
            $action,
            $description,
            $context,
            (int) ($user['id'] ?? 0) ?: null,
            (int) ($user['unit_id'] ?? 0) ?: null,
            $targetType,
            $targetId === null ? null : (string) $targetId
        );
    }

    public static function sessionToken(): ?string
    {
        return (string) (Auth::user()['session_login_token'] ?? '') ?: null;
    }

    public static function ipAddress(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }
}
