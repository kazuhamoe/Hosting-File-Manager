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

    /**
     * Membaca log dari bawah (terbaru di atas) dan memparsing setiap baris.
     * Format baris: [datetime] [ip] [ACTION] target | Status: STATUS | Detail: detail
     *
     * @param int    $limit  Maks baris yang dikembalikan
     * @param string $filter Filter string (cari di action/target/detail)
     * @param string $action Filter berdasarkan jenis aksi (kosong = semua)
     */
    public static function getLogs(int $limit = 200, string $filter = '', string $actionFilter = ''): array
    {
        $logFile = defined('LOG_FILE') ? LOG_FILE : (__DIR__ . '/../storage/logs/audit.log');

        if (!file_exists($logFile)) {
            return ['success' => true, 'logs' => [], 'total' => 0, 'file_exists' => false];
        }

        // Baca semua baris
        $rawLines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($rawLines === false) {
            return ['success' => false, 'message' => 'Gagal membaca file log.'];
        }

        // Balik urutan — terbaru di atas
        $rawLines = array_reverse($rawLines);

        $parsed = [];
        $filterLower = $filter !== '' ? mb_strtolower($filter) : '';
        $actionFilterUpper = $actionFilter !== '' ? strtoupper(trim($actionFilter)) : '';

        foreach ($rawLines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            // Parse: [datetime] [ip] [ACTION] target | Status: STATUS | Detail: detail
            $entry = [
                'datetime' => '',
                'ip'       => '',
                'action'   => '',
                'target'   => '',
                'status'   => '',
                'detail'   => '',
                'raw'      => $line,
            ];

            // Datetime
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
                $entry['datetime'] = $m[1];
            }

            // IP
            if (preg_match('/\]\s*\[([^\]]+)\]\s*\[/', $line, $m)) {
                $entry['ip'] = $m[1];
            }

            // Action (third bracket)
            if (preg_match('/\]\s*\[([A-Z_]+)\]\s/', $line, $m)) {
                $entry['action'] = $m[1];
            }

            // Target (between action bracket and pipe)
            if (preg_match('/\]\s*\[([A-Z_]+)\]\s+(.+?)\s*\|\s*Status:/', $line, $m)) {
                $entry['target'] = trim($m[2]);
            } elseif (preg_match('/\]\s*\[([A-Z_]+)\]\s+(.+)$/', $line, $m)) {
                $entry['target'] = trim($m[2]);
            }

            // Status
            if (preg_match('/\|\s*Status:\s*([A-Z_]+)/', $line, $m)) {
                $entry['status'] = $m[1];
            }

            // Detail
            if (preg_match('/\|\s*Detail:\s*(.+)$/', $line, $m)) {
                $entry['detail'] = trim($m[1]);
            }

            // Filter by action type
            if ($actionFilterUpper !== '' && $entry['action'] !== $actionFilterUpper) {
                continue;
            }

            // Filter by text
            if ($filterLower !== '') {
                $haystack = mb_strtolower($entry['action'] . ' ' . $entry['target'] . ' ' . $entry['detail']);
                if (mb_strpos($haystack, $filterLower) === false) {
                    continue;
                }
            }

            $parsed[] = $entry;

            if (count($parsed) >= $limit) break;
        }

        return [
            'success' => true,
            'logs'    => $parsed,
            'total'   => count($parsed),
            'file_exists' => true,
            'log_size' => filesize($logFile),
            'log_size_human' => self::formatBytes(filesize($logFile)),
        ];
    }

    /**
     * Menghapus seluruh isi audit log.
     */
    public static function clearLog(): array
    {
        $logFile = defined('LOG_FILE') ? LOG_FILE : (__DIR__ . '/../storage/logs/audit.log');
        if (!file_exists($logFile)) {
            return ['success' => true, 'message' => 'Log sudah kosong.'];
        }
        if (@file_put_contents($logFile, '') === false) {
            return ['success' => false, 'message' => 'Gagal menghapus log.'];
        }
        // Tulis entry baru bahwa log dibersihkan
        self::log('CLEAR_LOG', 'audit.log', 'SUCCESS', 'Log dibersihkan oleh administrator');
        return ['success' => true, 'message' => 'Activity log berhasil dibersihkan.'];
    }

    /**
     * Format bytes menjadi human-readable (helper internal).
     */
    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 2) . ' MB';
    }
}
