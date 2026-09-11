<?php
if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access denied.');
}

/**
 * Minimal Audit Logger
 * Mencatat aktivitas penting filesystem tanpa membocorkan credential.
 */

class Logger
{
    public static function log(string $action, string $target, string $status = 'SUCCESS', string $message = ''): void
    {
        $logFile = defined('LOG_FILE') ? LOG_FILE : (__DIR__ . '/../storage/logs/audit.log');
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $time = date('Y-m-d H:i:s');
        $action = strtoupper(trim($action));
        $status = strtoupper(trim($status));
        // Bersihkan target agar tidak ada baris baru
        $target = str_replace(["\r", "\n"], ' ', $target);
        $message = str_replace(["\r", "\n"], ' ', $message);

        $line = sprintf(
            "[%s] [%s] [%s] %s | Status: %s%s" . PHP_EOL,
            $time,
            $ip,
            $action,
            $target,
            $status,
            $message !== '' ? " | Detail: $message" : ""
        );

        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
