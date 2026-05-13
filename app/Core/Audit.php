<?php

namespace App\Core;

class Audit
{
    private static function logPath(): string
    {
        $customPath = trim((string) ($_ENV['AUDIT_LOG_PATH'] ?? ''));
        if ($customPath !== '') {
            return $customPath;
        }

        $mes = date('Y-m');
        return __DIR__ . "/../../storage/logs/audit-{$mes}.log";
    }

    private static function ensureDir(string $filePath): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private static function clientIp(): string
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

    public static function write(string $action, string $status, ?string $target = null, array $details = []): void
    {
        $path = self::logPath();
        self::ensureDir($path);

        $actor = $_SESSION['usuario'] ?? [];
        $entry = [
            'ts' => date('c'),
            'action' => $action,
            'status' => $status,
            'target' => $target,
            'actor_matricula' => $actor['matricula'] ?? null,
            'actor_tipo' => $actor['tipo'] ?? null,
            'ip' => self::clientIp(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'details' => $details,
        ];

        @file_put_contents($path, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
