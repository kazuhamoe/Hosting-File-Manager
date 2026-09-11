<?php
if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access denied.');
}

// Polyfills untuk kompatibilitas PHP < 8.0
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

/**
 * Security & Path Sandbox Layer
 * Menangani validasi boundary filesystem, pencegahan path traversal, dan sanitasi.
 */

class Security
{
    private static $canonicalRoot = null;

    /**
     * Mendapatkan canonical absolute path dari ALLOWED_ROOT.
     */
    public static function getRoot(): string
    {
        if (self::$canonicalRoot === null) {
            $root = defined('ALLOWED_ROOT') ? ALLOWED_ROOT : (__DIR__ . '/../sandbox');
            if (!file_exists($root)) {
                @mkdir($root, 0755, true);
            }
            $real = realpath($root);
            if ($real === false) {
                // Fallback jika realpath gagal karena open_basedir, symlink hosting, dsb.
                if (file_exists($root) && is_dir($root)) {
                    $real = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $root);
                } else {
                    $real = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
                }
            }
            self::$canonicalRoot = rtrim($real, DIRECTORY_SEPARATOR);
        }
        return self::$canonicalRoot;
    }

    /**
     * Normalisasi string relative path tanpa mengizinkan '..' keluar dari root.
     * Mencegah URL encoding traversal (%2e%2e, %252e%252e), null byte, dan separator injection.
     */
    public static function normalizeRelativePath(string $path): ?string
    {
        // Deteksi dan tolak multi-layer URL encoding traversal (%2e, %252e, dll)
        $checkPath = $path;
        while (strpos($checkPath, '%') !== false) {
            $decoded = rawurldecode($checkPath);
            if ($decoded === $checkPath) break;
            if (strpos($decoded, '..') !== false || strpos($decoded, '/') !== false || strpos($decoded, '\\') !== false) {
                return null;
            }
            $checkPath = $decoded;
        }

        // Hapus null byte
        $path = str_replace(chr(0), '', $path);
        // Normalisasi separator
        $path = str_replace(['\\', '/'], '/', $path);
        
        $parts = explode('/', $path);
        $safeParts = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (empty($safeParts)) {
                    // Percobaan keluar dari root (traversal attempt)
                    return null;
                }
                array_pop($safeParts);
            } else {
                // Jangan izinkan karakter drive letter berbahaya seperti C:
                if (preg_match('/^[a-zA-Z]:$/', $part)) {
                    return null;
                }
                $safeParts[] = $part;
            }
        }

        return implode(DIRECTORY_SEPARATOR, $safeParts);
    }

    /**
     * Menyelesaikan relative path menjadi absolute server path yang aman di dalam ALLOWED_ROOT.
     *
     * @param string $relativePath Path dari request user
     * @param bool $mustExist Apakah file/folder target harus sudah ada di disk
     * @return string|null Absolute path yang sudah tervalidasi atau null jika melanggar boundary
     */
    public static function resolvePath(string $relativePath, bool $mustExist = true): ?string
    {
        $normalized = self::normalizeRelativePath($relativePath);
        if ($normalized === null) {
            return null; // Terdeteksi path traversal
        }

        $root = self::getRoot();

        // Jika path merujuk ke root itu sendiri ('/' atau '')
        if ($normalized === '') {
            return $root;
        }

        $target = $root . DIRECTORY_SEPARATOR . $normalized;

        // Jika path memiliki prefix nama root directory (contoh: /public_html/uploads ketika root adalah /public_html)
        $rootBase = basename($root);
        $parts = explode(DIRECTORY_SEPARATOR, $normalized);
        $hasRootPrefix = (!empty($parts) && strcasecmp($parts[0], $rootBase) === 0);

        $realTarget = realpath($target);
        if ($realTarget === false && $hasRootPrefix) {
            $strippedRel = implode(DIRECTORY_SEPARATOR, array_slice($parts, 1));
            $altTarget = $root . ($strippedRel !== '' ? DIRECTORY_SEPARATOR . $strippedRel : '');
            $realTarget = realpath($altTarget);
            if ($realTarget !== false) {
                $target = $altTarget;
            }
        }

        // Jika target fisik sudah ada di disk
        if ($realTarget !== false) {
            if (!self::isInsideRoot($realTarget, $root)) {
                return null;
            }
            return $realTarget;
        }

        // Jika target belum ada dan diharuskan sudah ada
        if ($mustExist) {
            return null;
        }

        // Target belum ada di disk (contoh: upload file baru, mkdir, atau folder ekstraksi baru)
        $parent = dirname($target);
        $realParent = realpath($parent);

        if (($realParent === false || !self::isInsideRoot($realParent, $root)) && $hasRootPrefix) {
            $strippedRel = implode(DIRECTORY_SEPARATOR, array_slice($parts, 1));
            $target = $root . ($strippedRel !== '' ? DIRECTORY_SEPARATOR . $strippedRel : '');
            $parent = dirname($target);
            $realParent = realpath($parent);
        }

        if ($realParent === false || !self::isInsideRoot($realParent, $root)) {
            return null;
        }

        $targetBasename = basename($target);
        if (!self::isValidFilename($targetBasename)) {
            return null;
        }

        return $realParent . DIRECTORY_SEPARATOR . $targetBasename;
    }

    /**
     * Memeriksa apakah $path berada di dalam $root secara kanonikal.
     */
    public static function isInsideRoot(string $path, ?string $root = null): bool
    {
        $root = $root ?? self::getRoot();
        // Normalisasi separator agar konsisten lintas OS (Windows / Linux)
        $normPath = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $normRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);

        if (strcasecmp($normPath, $normRoot) === 0) {
            return true;
        }

        // Prefix match diikuti DIRECTORY_SEPARATOR
        $prefix = $normRoot . DIRECTORY_SEPARATOR;
        if (stripos($normPath, $prefix) === 0) {
            return true;
        }

        return false;
    }

    /**
     * Mengonversi absolute server path menjadi virtual path relatif terhadap ALLOWED_ROOT.
     * Mencegah absolute server path bocor ke browser client.
     */
    public static function toVirtualPath(string $absolutePath): string
    {
        $root = self::getRoot();
        $path = rtrim($absolutePath, DIRECTORY_SEPARATOR);

        if (strcasecmp($path, $root) === 0) {
            return '/';
        }

        $rootLen = strlen($root);
        if (stripos($path, $root) === 0) {
            $sub = substr($path, $rootLen);
            $virtual = str_replace('\\', '/', $sub);
            return '/' . ltrim($virtual, '/');
        }

        return '/';
    }

    /**
     * Memeriksa apakah file/folder termasuk dalam daftar yang dilindungi.
     * Mencegah modifikasi/penghapusan file inti File Manager dan file sensitif sistem.
     * Aturan perlindungan hanya berlaku untuk file inti sistem / file di root level,
     * BUKAN memblokir file biasa di dalam subdirektori (misal: admin/.htaccess, uploads/index.php).
     */
    public static function isProtected(string $relativePath): bool
    {
        $normalized = self::normalizeRelativePath($relativePath);
        if ($normalized === null || $normalized === '') {
            return false;
        }

        $parts = explode(DIRECTORY_SEPARATOR, $normalized);
        $rootSegment = strtolower($parts[0]);

        // Daftar protected file/folder di root level
        $rootProtected = ['index.php', 'config.php', '.htaccess', '.htpasswd', '.git', 'app', 'storage', 'tests'];

        if (defined('PROTECTED_FILES') && is_array(PROTECTED_FILES)) {
            foreach (PROTECTED_FILES as $pItem) {
                $pClean = strtolower(trim(str_replace(['/', '\\'], '', $pItem)));
                if ($pClean !== '' && !in_array($pClean, $rootProtected, true)) {
                    $rootProtected[] = $pClean;
                }
            }
        }

        // Folder internal aplikasi yang diproteksi beserta seluruh sub-file/subfolder di dalamnya
        $internalDirs = ['app', 'storage', '.git'];

        // 1. Jika path berada tepat di root level (1 segmen saja, contoh: 'index.php', 'config.php', 'app')
        if (count($parts) === 1) {
            if (in_array($rootSegment, $rootProtected, true)) {
                return true;
            }
        } else {
            // 2. Jika path berada di subdirektori:
            // HANYA terproteksi jika segmen pertamanya adalah folder internal sistem (contoh: app/Security.php, storage/logs/audit.log)
            if (in_array($rootSegment, $internalDirs, true)) {
                return true;
            }
            // Subfolder biasa (contoh: ada/admin/.htaccess, uploads/index.php, my-site/app/controller.php) BUKAN protected!
        }

        // 3. Verifikasi kanonikal fisik terhadap instalasi File Manager itu sendiri
        $appDir = realpath(dirname(__DIR__));
        if ($appDir !== false) {
            $root = self::getRoot();
            $target = $root . DIRECTORY_SEPARATOR . $normalized;
            $realTarget = realpath($target);

            $indexReal = realpath($appDir . DIRECTORY_SEPARATOR . 'index.php');
            $configReal = realpath($appDir . DIRECTORY_SEPARATOR . 'config.php');
            $appReal = realpath($appDir . DIRECTORY_SEPARATOR . 'app');
            $storageReal = realpath($appDir . DIRECTORY_SEPARATOR . 'storage');

            if ($realTarget !== false) {
                if ($indexReal !== false && strcasecmp($realTarget, $indexReal) === 0) return true;
                if ($configReal !== false && strcasecmp($realTarget, $configReal) === 0) return true;
                if ($appReal !== false && self::isInsideRoot($realTarget, $appReal)) return true;
                if ($storageReal !== false && self::isInsideRoot($realTarget, $storageReal)) return true;
            }
        }

        return false;
    }

    /**
     * Memeriksa apakah calon target ekstraksi ZIP mencoba menimpa file terproteksi sistem.
     * Sesuai Aturan 7:
     * - Jika ZIP membuat file baru -> ALLOW.
     * - Jika ZIP menimpa file biasa -> ALLOW (diatur oleh strategi konflik: overwrite, skip, rename).
     * - Hanya jika ZIP mencoba menimpa file terproteksi sistem atau menginjeksi direktori core -> REJECT.
     *
     * @param string $candidateTarget Absolute server path calon file ekstraksi
     * @param string $virtualRelativePath Virtual relative path calon file ekstraksi
     * @return array [ 'is_protected' => bool, 'reason' => string ]
     */
    public static function checkExtractProtection(string $candidateTarget, string $virtualRelativePath): array
    {
        // Berkas website biasa (index.php, .htaccess, config.php, app/, assets/, vendor/, storage/, dll.)
        // 100% diizinkan untuk diekstrak sesuai strategi konflik pengguna (overwrite/skip/rename).
        // Seluruh proteksi keamanan riil (Zip Slip, Path Traversal, Null Byte, Escapes dari ALLOWED_ROOT)
        // telah diverifikasi tuntas di validateArchiveEntries() sebelum mencapai tahap ini.
        return ['is_protected' => false, 'reason' => ''];
    }

    /**
     * Memvalidasi apakah nama file valid dan aman.
     * Memblokir Windows reserved names, control characters, ADS (:), dan trailing dot/space bypass.
     */
    public static function isValidFilename(string $filename): bool
    {
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return false;
        }

        // Tolak trailing spaces atau trailing dots (Windows bypass)
        if (str_ends_with($filename, ' ') || str_ends_with($filename, '.')) {
            return false;
        }

        // Cegah karakter kontrol dan karakter ilegal filesystem
        if (preg_match('/[\x00-\x1f\x7f\x80-\x9f\/\?\\\\:\*\"<>\|]/', $filename)) {
            return false;
        }

        // Cek nama reserved device Windows (CON, PRN, AUX, NUL, COM1-9, LPT1-9)
        $nameOnly = strtoupper(pathinfo($filename, PATHINFO_FILENAME));
        $reserved = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
        if (in_array($nameOnly, $reserved, true)) {
            return false;
        }

        return true;
    }

    /**
     * Membersihkan nama file dari karakter berbahaya.
     */
    public static function sanitizeFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[\x00-\x1f\x7f\x80-\x9f\/\?\\\\:\*\"<>\|]/', '_', $filename);
        $filename = trim($filename, ". \t\n\r\0\x0B");
        if ($filename === '') {
            $filename = 'unnamed_' . time();
        }
        return $filename;
    }

    /**
     * Memformat ukuran byte ke satuan yang mudah dibaca (KB, MB, GB).
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int)floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / pow(1024, $power);
        return round($value, $precision) . ' ' . $units[$power];
    }

    /**
     * Mengonversi string format byte (seperti '40M', '256M', '1G', '512K') ke integer byte.
     */
    public static function parseBytes(string $val): int
    {
        $val = trim($val);
        if ($val === '') return 0;
        $last = strtolower($val[strlen($val) - 1]);
        $num = (int)$val;
        switch ($last) {
            case 'g': $num *= 1024 * 1024 * 1024; break;
            case 'm': $num *= 1024 * 1024; break;
            case 'k': $num *= 1024; break;
        }
        return $num;
    }

    /**
     * Mengambil informasi limit konfigurasi upload server secara aman tanpa membocorkan path sensitif.
     */
    public static function getServerUploadLimits(): array
    {
        $postMax = ini_get('post_max_size') ?: '0';
        $uploadMax = ini_get('upload_max_filesize') ?: '0';
        $memLimit = ini_get('memory_limit') ?: '0';
        $maxExec = ini_get('max_execution_time') ?: '0';
        $maxInput = ini_get('max_input_time') ?: '0';

        $postBytes = self::parseBytes($postMax);
        $uploadBytes = self::parseBytes($uploadMax);
        
        $effectiveLimit = min($postBytes > 0 ? $postBytes : PHP_INT_MAX, $uploadBytes > 0 ? $uploadBytes : PHP_INT_MAX);
        if (defined('MAX_UPLOAD_SIZE') && MAX_UPLOAD_SIZE > 0) {
            $effectiveLimit = min($effectiveLimit, MAX_UPLOAD_SIZE);
        }

        $tmpDir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        $tmpWritable = @is_writable($tmpDir);

        $can101Mb = ($postBytes >= 101 * 1024 * 1024) && ($uploadBytes >= 101 * 1024 * 1024);

        return [
            'php_version' => PHP_VERSION,
            'post_max_size' => $postMax,
            'post_max_size_bytes' => $postBytes,
            'upload_max_filesize' => $uploadMax,
            'upload_max_filesize_bytes' => $uploadBytes,
            'effective_limit_bytes' => $effectiveLimit,
            'effective_limit_human' => self::formatBytes($effectiveLimit),
            'max_execution_time' => $maxExec . 's',
            'max_input_time' => $maxInput . 's',
            'memory_limit' => $memLimit,
            'upload_tmp_dir_status' => $tmpWritable ? 'Tersedia & Writable' : 'Peringatan: Read-only atau tidak ditemukan',
            'app_max_upload_size' => defined('MAX_UPLOAD_SIZE') ? self::formatBytes(MAX_UPLOAD_SIZE) : '200 MB',
            'can_upload_101mb' => $can101Mb,
            'warning' => !$can101Mb ? "Batas server saat ini (upload_max_filesize: $uploadMax, post_max_size: $postMax) lebih kecil dari 101 MB. Upload berkas 101 MB atau lebih memerlukan penyesuaian php.ini hosting." : null
        ];
    }

    /**
     * Memformat permission Unix file (contoh: 0755, 0644, -rw-r--r--).
     */
    public static function formatPermissions(int $perms): array
    {
        // Format octal
        $octal = substr(sprintf('%o', $perms), -4);

        // Format rwx
        switch ($perms & 0xF000) {
            case 0xC000: $info = 's'; break; // socket
            case 0xA000: $info = 'l'; break; // symbolic link
            case 0x8000: $info = '-'; break; // regular
            case 0x6000: $info = 'b'; break; // block special
            case 0x4000: $info = 'd'; break; // directory
            case 0x2000: $info = 'c'; break; // character special
            case 0x1000: $info = 'p'; break; // FIFO pipe
            default:     $info = 'u'; break; // unknown
        }

        // Owner
        $info .= (($perms & 0x0100) ? 'r' : '-');
        $info .= (($perms & 0x0080) ? 'w' : '-');
        $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));

        // Group
        $info .= (($perms & 0x0020) ? 'r' : '-');
        $info .= (($perms & 0x0010) ? 'w' : '-');
        $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));

        // Others
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));

        return [
            'octal' => $octal,
            'rwx'   => $info,
        ];
    }

    /**
     * Memeriksa apakah file bisa dipratinjau sebagai plain text.
     */
    public static function isTextPreviewable(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = defined('TEXT_PREVIEW_EXTENSIONS') ? TEXT_PREVIEW_EXTENSIONS : ['txt', 'html', 'css', 'js', 'json', 'php', 'md'];
        return in_array($ext, $allowed, true);
    }
}
