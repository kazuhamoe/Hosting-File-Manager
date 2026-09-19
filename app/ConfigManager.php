<?php
/**
 * Hosting File Manager & ZIP Extractor
 * ConfigManager - Pengelolaan Konfigurasi Dinamis (config.php & storage/settings.json)
 */

class ConfigManager
{
    /**
     * Mendapatkan path lengkap ke berkas storage/settings.json
     */
    public static function getSettingsFilePath(): string
    {
        if (defined('SETTINGS_FILE')) {
            return SETTINGS_FILE;
        }
        $storageDir = defined('STORAGE_PATH') ? STORAGE_PATH : (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage');
        return $storageDir . DIRECTORY_SEPARATOR . 'settings.json';
    }

    /**
     * Mendapatkan path root bawaan yang dideteksi otomatis oleh sistem
     */
    public static function getDetectedDefaultRoot(): string
    {
        $base = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
        $parent = dirname($base);
        if (@is_dir($parent) && @is_readable($parent)) {
            if (is_dir($parent . DIRECTORY_SEPARATOR . 'public_html')) {
                return $parent;
            } elseif (basename($parent) === 'public_html') {
                return $parent;
            }
        }
        return $base;
    }

    /**
     * Membaca seluruh konfigurasi sistem aktif saat ini
     */
    public static function getAllSettings(): array
    {
        $settingsFile = self::getSettingsFilePath();
        $custom = file_exists($settingsFile) ? @json_decode(file_get_contents($settingsFile), true) : [];
        if (!is_array($custom)) {
            $custom = [];
        }

        $activeRoot = defined('ALLOWED_ROOT') ? ALLOWED_ROOT : self::getDetectedDefaultRoot();
        $activeSessionTimeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 2592000;
        $activeMaxUpload = defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : (200 * 1024 * 1024);
        $activeShowDisk = defined('SHOW_DISK_USAGE') ? (bool)SHOW_DISK_USAGE : false;
        $activeDiskQuota = defined('DISK_QUOTA_MB') ? DISK_QUOTA_MB : 0;

        $serverLimits = Security::getServerUploadLimits();

        return [
            'allowed_root'           => $custom['allowed_root'] ?? $activeRoot,
            'active_allowed_root'    => $activeRoot,
            'default_root'           => self::getDetectedDefaultRoot(),
            'session_timeout'        => isset($custom['session_timeout']) ? (int)$custom['session_timeout'] : $activeSessionTimeout,
            'max_upload_size'        => isset($custom['max_upload_size']) ? (int)$custom['max_upload_size'] : $activeMaxUpload,
            'max_upload_size_mb'     => isset($custom['max_upload_size_mb']) ? (int)$custom['max_upload_size_mb'] : (int)round((isset($custom['max_upload_size']) ? (int)$custom['max_upload_size'] : $activeMaxUpload) / (1024 * 1024)),
            'show_disk_usage'        => isset($custom['show_disk_usage']) ? (bool)$custom['show_disk_usage'] : $activeShowDisk,
            'disk_quota_mb'          => isset($custom['disk_quota_mb']) ? (int)$custom['disk_quota_mb'] : $activeDiskQuota,
            'server_limits'          => $serverLimits,
            'is_customized'          => !empty($custom)
        ];
    }

    /**
     * Menyimpan konfigurasi sistem ke storage/settings.json dan menyinkronkan ke config.php
     *
     * @param array $input Array konfigurasi baru
     * @param string $currentPassword Password saat ini untuk verifikasi keamanan
     * @return array Status keberhasilan
     */
    public static function saveSettings(array $input, string $currentPassword): array
    {
        // 1. Verifikasi kredensial via Auth
        if (!Auth::verifyPassword($currentPassword)) {
            Logger::log('SETTINGS', $_SESSION['hfm_username'] ?? 'unknown', 'FAILED', 'Password konfirmasi salah saat menyimpan konfigurasi');
            return ['success' => false, 'message' => 'Password saat ini salah. Perubahan konfigurasi dibatalkan.'];
        }

        // 2. Validasi ALLOWED_ROOT
        $targetRoot = trim((string)($input['allowed_root'] ?? ''));
        if ($targetRoot !== '') {
            $realTargetRoot = realpath($targetRoot);
            if ($realTargetRoot === false || !is_dir($realTargetRoot)) {
                return [
                    'success' => false,
                    'message' => "Direktori root '$targetRoot' tidak valid atau tidak ditemukan pada server hosting."
                ];
            }
            $targetRoot = $realTargetRoot;
        } else {
            $targetRoot = self::getDetectedDefaultRoot();
        }

        // 3. Validasi SESSION_TIMEOUT
        $sessionTimeout = isset($input['session_timeout']) ? (int)$input['session_timeout'] : 2592000;
        if ($sessionTimeout < 300) {
            return [
                'success' => false,
                'message' => 'Masa aktif sesi (Session Timeout) minimal 300 detik (5 menit).'
            ];
        }

        // 4. Validasi MAX_UPLOAD_SIZE (dalam MB)
        $maxUploadMb = isset($input['max_upload_size_mb']) ? (int)$input['max_upload_size_mb'] : 200;
        if ($maxUploadMb < 0) {
            $maxUploadMb = 200;
        }
        $maxUploadBytes = $maxUploadMb * 1024 * 1024;

        // 5. Validasi SHOW_DISK_USAGE
        $showDiskUsage = !empty($input['show_disk_usage']) && ($input['show_disk_usage'] === true || $input['show_disk_usage'] === 'true' || $input['show_disk_usage'] === '1' || $input['show_disk_usage'] === 1);

        // 6. Validasi DISK_QUOTA_MB
        $diskQuotaMb = isset($input['disk_quota_mb']) ? (int)$input['disk_quota_mb'] : 0;
        if ($diskQuotaMb < 0) {
            $diskQuotaMb = 0;
        }

        // 7. Simpan ke storage/settings.json
        $settingsFile = self::getSettingsFilePath();
        $dir = dirname($settingsFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @chmod($dir, 0777);

        $dataToSave = [
            'allowed_root'       => $targetRoot,
            'session_timeout'    => $sessionTimeout,
            'max_upload_size'    => $maxUploadBytes,
            'max_upload_size_mb' => $maxUploadMb,
            'show_disk_usage'    => $showDiskUsage,
            'disk_quota_mb'      => $diskQuotaMb,
            'updated_at'         => date('Y-m-d H:i:s'),
            'updated_by_ip'      => Auth::getClientIp()
        ];

        $saved = @file_put_contents($settingsFile, json_encode($dataToSave, JSON_PRETTY_PRINT), LOCK_EX);
        if ($saved === false) {
            return [
                'success' => false,
                'message' => 'Gagal menyimpan konfigurasi ke storage/settings.json. Periksa izin folder storage/.'
            ];
        }
        @chmod($settingsFile, 0777);

        // 8. Sinkronisasi perubahan ke config.php jika berkas writable
        self::syncToConfigFile($dataToSave);

        Logger::log('CONFIG', 'System Settings', 'SUCCESS', "Konfigurasi diperbarui: Root=$targetRoot, Timeout=$sessionTimeout, MaxUpload={$maxUploadMb}MB, Disk=" . ($showDiskUsage ? 'ON' : 'OFF'));

        return [
            'success'  => true,
            'message'  => 'Konfigurasi sistem berhasil disimpan dan diterapkan.',
            'settings' => $dataToSave
        ];
    }

    /**
     * Menulis override batas konfigurasi PHP (upload_max_filesize, post_max_size, memory_limit)
     * ke berkas .user.ini (PHP FastCGI/CGI/LiteSpeed) dan .htaccess (Apache mod_php).
     *
     * Catatan: upload_max_filesize & post_max_size adalah direktif PHP_INI_PERDIR yang tidak
     * dapat diubah via ini_set() saat runtime, sehingga harus ditulis ke berkas .ini/.htaccess.
     *
     * @param int $uploadMb upload_max_filesize dalam MB
     * @param int $postMb   post_max_size dalam MB
     * @param int $memMb    memory_limit dalam MB
     * @return array Status penulisan tiap berkas
     */
    public static function applyPhpIniOverride(int $uploadMb, int $postMb, int $memMb): array
    {
        // Batasi rentang nilai yang wajar untuk mencegah kesalahan input
        $uploadMb = max(1, min(10240, $uploadMb));
        $postMb   = max(1, min(10240, $postMb));
        $memMb    = max(16, min(20480, $memMb));

        // post_max_size disarankan >= upload_max_filesize agar upload tidak terpotong
        if ($postMb < $uploadMb) {
            $postMb = $uploadMb;
        }
        // memory_limit sebaiknya >= post_max_size
        if ($memMb < $postMb) {
            $memMb = $postMb;
        }

        $baseDir = dirname(__DIR__);
        $results = [];
        $marker  = 'Hosting File Manager - PHP Limit Override';

        // 1. Tulis / perbarui .user.ini
        $userIniFile = $baseDir . DIRECTORY_SEPARATOR . '.user.ini';
        $userIniBlock = "; BEGIN {$marker}\n"
            . "upload_max_filesize = {$uploadMb}M\n"
            . "post_max_size = {$postMb}M\n"
            . "memory_limit = {$memMb}M\n"
            . "; END {$marker}\n";

        $existingUserIni = file_exists($userIniFile) ? (string)@file_get_contents($userIniFile) : '';
        $cleanUserIni = preg_replace(
            '/; BEGIN ' . preg_quote($marker, '/') . '.*?; END ' . preg_quote($marker, '/') . '\s*/s',
            '',
            $existingUserIni
        );
        $newUserIni = rtrim((string)$cleanUserIni) === '' ? $userIniBlock : rtrim((string)$cleanUserIni) . "\n\n" . $userIniBlock;
        $results['user_ini'] = @file_put_contents($userIniFile, $newUserIni, LOCK_EX) !== false;

        // 2. Tulis / perbarui .htaccess (blok php_value untuk Apache mod_php)
        $htaccessFile = $baseDir . DIRECTORY_SEPARATOR . '.htaccess';
        $htBlock = "# BEGIN {$marker}\n"
            . "<IfModule mod_php.c>\n"
            . "    php_value upload_max_filesize {$uploadMb}M\n"
            . "    php_value post_max_size {$postMb}M\n"
            . "    php_value memory_limit {$memMb}M\n"
            . "</IfModule>\n"
            . "<IfModule mod_php7.c>\n"
            . "    php_value upload_max_filesize {$uploadMb}M\n"
            . "    php_value post_max_size {$postMb}M\n"
            . "    php_value memory_limit {$memMb}M\n"
            . "</IfModule>\n"
            . "<IfModule mod_php8.c>\n"
            . "    php_value upload_max_filesize {$uploadMb}M\n"
            . "    php_value post_max_size {$postMb}M\n"
            . "    php_value memory_limit {$memMb}M\n"
            . "</IfModule>\n"
            . "<IfModule php_module>\n"
            . "    php_value upload_max_filesize {$uploadMb}M\n"
            . "    php_value post_max_size {$postMb}M\n"
            . "    php_value memory_limit {$memMb}M\n"
            . "</IfModule>\n"
            . "<IfModule php8_module>\n"
            . "    php_value upload_max_filesize {$uploadMb}M\n"
            . "    php_value post_max_size {$postMb}M\n"
            . "    php_value memory_limit {$memMb}M\n"
            . "</IfModule>\n"
            . "# END {$marker}\n";

        $existingHt = file_exists($htaccessFile) ? (string)@file_get_contents($htaccessFile) : '';
        $cleanHt = preg_replace(
            '/# BEGIN ' . preg_quote($marker, '/') . '.*?# END ' . preg_quote($marker, '/') . '\s*/s',
            '',
            $existingHt
        );
        $newHt = rtrim((string)$cleanHt) === '' ? $htBlock : rtrim((string)$cleanHt) . "\n\n" . $htBlock;
        $results['htaccess'] = @file_put_contents($htaccessFile, $newHt, LOCK_EX) !== false;

        // 3. Coba terapkan memory_limit secara runtime (satu-satunya yang bisa via ini_set)
        @ini_set('memory_limit', $memMb . 'M');

        return [
            'upload_max_filesize' => $uploadMb . 'M',
            'post_max_size'       => $postMb . 'M',
            'memory_limit'        => $memMb . 'M',
            'user_ini_written'    => $results['user_ini'],
            'htaccess_written'    => $results['htaccess'],
            'any_written'         => ($results['user_ini'] || $results['htaccess'])
        ];
    }

    /**
     * Sinkronisasi nilai konfigurasi ke berkas config.php agar file kode tetap up-to-date
     */
    private static function syncToConfigFile(array $settings): void
    {
        $configFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.php';
        if (!file_exists($configFile) || !is_writable($configFile)) {
            return;
        }

        $content = @file_get_contents($configFile);
        if (!$content) {
            return;
        }

        // Update SESSION_TIMEOUT
        $content = preg_replace(
            "/define\s*\(\s*['\"]SESSION_TIMEOUT['\"]\s*,\s*[^)]+\s*\);/",
            "define('SESSION_TIMEOUT', " . (int)$settings['session_timeout'] . ");",
            $content
        );

        // Update MAX_UPLOAD_SIZE
        $content = preg_replace(
            "/define\s*\(\s*['\"]MAX_UPLOAD_SIZE['\"]\s*,\s*[^)]+\s*\);/",
            "define('MAX_UPLOAD_SIZE', " . (int)$settings['max_upload_size'] . "); // " . (int)$settings['max_upload_size_mb'] . " MB",
            $content
        );

        // Update SHOW_DISK_USAGE
        $showDiskStr = $settings['show_disk_usage'] ? 'true' : 'false';
        $content = preg_replace(
            "/define\s*\(\s*['\"]SHOW_DISK_USAGE['\"]\s*,\s*[^)]+\s*\);/",
            "define('SHOW_DISK_USAGE', $showDiskStr);",
            $content
        );

        // Update DISK_QUOTA_MB
        $content = preg_replace(
            "/define\s*\(\s*['\"]DISK_QUOTA_MB['\"]\s*,\s*[^)]+\s*\);/",
            "define('DISK_QUOTA_MB', " . (int)$settings['disk_quota_mb'] . ");",
            $content
        );

        @file_put_contents($configFile, $content, LOCK_EX);
    }
}
