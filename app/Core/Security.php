<?php

namespace App\Core;

class Security
{
    private static function storageDir(): string
    {
        $dir = sys_get_temp_dir() . '/refeitorio_rate_limit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir;
    }

    private static function normalizeKey(string $scope, string $key): string
    {
        $scopeNorm = preg_replace('/[^a-zA-Z0-9_-]/', '_', $scope) ?: 'default';
        $keyNorm = trim(strtolower($key));
        if ($keyNorm === '') {
            $keyNorm = '_empty_';
        }
        return hash('sha256', $scopeNorm . '|' . $keyNorm);
    }

    private static function filePath(string $scope, string $key): string
    {
        return self::storageDir() . '/' . self::normalizeKey($scope, $key) . '.json';
    }

    private static function loadState(string $scope, string $key): array
    {
        $path = self::filePath($scope, $key);
        if (!is_file($path)) {
            return ['attempts' => [], 'locked_until' => 0];
        }

        $raw = file_get_contents($path);
        $decoded = json_decode($raw ?: '', true);

        if (!is_array($decoded)) {
            return ['attempts' => [], 'locked_until' => 0];
        }

        return [
            'attempts' => is_array($decoded['attempts'] ?? null) ? $decoded['attempts'] : [],
            'locked_until' => (int) ($decoded['locked_until'] ?? 0),
        ];
    }

    private static function saveState(string $scope, string $key, array $state): void
    {
        $path = self::filePath($scope, $key);
        file_put_contents($path, json_encode($state, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public static function clientIp(): string
    {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff !== '') {
            $parts = explode(',', $xff);
            $first = trim($parts[0] ?? '');
            if ($first !== '') {
                return $first;
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function isBlocked(
        string $scope,
        string $key,
        int $windowSeconds,
        int $maxAttempts,
        int $lockoutSeconds
    ): int {
        $state = self::loadState($scope, $key);
        $now = time();

        if ((int) $state['locked_until'] > $now) {
            return (int) $state['locked_until'] - $now;
        }

        $attempts = array_values(array_filter(
            $state['attempts'],
            static fn($ts): bool => ($now - (int) $ts) <= $windowSeconds
        ));

        $state['attempts'] = $attempts;
        $state['locked_until'] = 0;

        // Se já excedeu o limite dentro da janela, ativa bloqueio imediatamente.
        if (count($attempts) >= $maxAttempts) {
            $state['attempts'] = [];
            $state['locked_until'] = $now + $lockoutSeconds;
            self::saveState($scope, $key, $state);
            return $lockoutSeconds;
        }

        self::saveState($scope, $key, $state);
        return 0;
    }

    public static function registerFailure(
        string $scope,
        string $key,
        int $windowSeconds,
        int $maxAttempts,
        int $lockoutSeconds
    ): int {
        $state = self::loadState($scope, $key);
        $now = time();

        if ((int) $state['locked_until'] > $now) {
            return (int) $state['locked_until'] - $now;
        }

        $attempts = array_values(array_filter(
            $state['attempts'],
            static fn($ts): bool => ($now - (int) $ts) <= $windowSeconds
        ));

        $attempts[] = $now;
        $state['attempts'] = $attempts;

        if (count($attempts) >= $maxAttempts) {
            $state['attempts'] = [];
            $state['locked_until'] = $now + $lockoutSeconds;
        } else {
            $state['locked_until'] = 0;
        }

        self::saveState($scope, $key, $state);

        return (int) max(0, $state['locked_until'] - $now);
    }

    public static function clear(string $scope, string $key): void
    {
        $path = self::filePath($scope, $key);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
