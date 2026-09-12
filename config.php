<?php
/**
 * Hosting File Manager & ZIP Extractor
 * Configuration File
 *
 * Silakan sesuaikan konfigurasi di bawah ini sesuai kebutuhan hosting Anda.
 */

// Hindari eksekusi langsung jika tidak diinginkan
if (!defined('APP_INIT') && basename($_SERVER['PHP_SELF'] ?? '') === 'config.php') {
    http_response_code(403);
    exit('Direct access denied.');
}

// --------------------------------------------------------------------------
// 1. FILESYSTEM ROOT (DIREKTORI HOSTING YANG DIKELOLA)
// --------------------------------------------------------------------------
/**
 * Tentukan root directory yang boleh dikelola oleh File Manager.
 * Semua operasi (browse, upload, extract, delete, rename, copy, move) dibatasi pada direktori ini.
 *
 * Logika Deteksi Hosting Otomatis:
 * 1. Jika direktori induk (parent) memuat folder 'public_html' (misal: /home/username/),
 *    maka root otomatis diarahkan ke user home (/home/username) agar Anda dapat
 *    mengakses seluruh public_html dan subdomain lain di akun hosting Anda.
 * 2. Jika script ditaruh di dalam subfolder public_html (misal: /home/username/public_html/up/),
 *    maka root otomatis diarahkan ke /home/username/public_html.
 * 3. Jika tidak, maka mengelola folder tempat script berada (__DIR__).
 *
 * Anda juga dapat menentukan path secara manual jika diinginkan, contoh:
 *   define('ALLOWED_ROOT', '/home/username/public_html');
 */
// Muat pengaturan dinamis dari storage/settings.json jika ada
$customSettingsFile = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'settings.json';
$customSettings = file_exists($customSettingsFile) ? @json_decode(file_get_contents($customSettingsFile), true) : [];
if (!is_array($customSettings)) {
    $customSettings = [];
}

$detectedRoot = __DIR__;
$parentDir = dirname(__DIR__);

if (!empty($customSettings['allowed_root']) && @is_dir($customSettings['allowed_root'])) {
    $detectedRoot = realpath($customSettings['allowed_root']) ?: $customSettings['allowed_root'];
} elseif (@is_dir($parentDir) && @is_readable($parentDir)) {
    if (is_dir($parentDir . DIRECTORY_SEPARATOR . 'public_html')) {
        $detectedRoot = $parentDir;
    } elseif (basename($parentDir) === 'public_html') {
        $detectedRoot = $parentDir;
    }
}

if (!defined('ALLOWED_ROOT')) {
    define('ALLOWED_ROOT', $detectedRoot);
}

// --------------------------------------------------------------------------
// 2. PROTECTED FILES & DIRECTORIES
// --------------------------------------------------------------------------
/**
 * Daftar file dan folder penting yang DILARANG untuk dihapus, di-rename,
 * dipindahkan, atau ditimpa saat ekstraksi ZIP.
 */
define('PROTECTED_FILES', [
    'index.php',
    'config.php',
    '.htaccess',
    '.htpasswd',
    '.git',
    'app',
]);

// --------------------------------------------------------------------------
// 3. AUTHENTICATION & CREDENTIALS
// --------------------------------------------------------------------------
/**
 * Kredensial login administrator.
 *
 * FITUR CREATE PASSWORD OTOMATIS (First-Time Setup Wizard):
 * Secara default AUTH_PASS_HASH dikosongkan ('') agar saat script pertama kali
 * diunggah ke hosting, sistem secara otomatis mengaktifkan wizard pembuatan akun
 * administrator ("Create Password"). Password yang dibuat pengguna akan disimpan
 * dalam bentuk hash Bcrypt aman di storage/credentials.json.
 *
 * Jika Anda ingin mengunci password secara statis melalui file ini, isi hash Bcrypt di bawah.
 */
define('AUTH_USER', 'admin');
define('AUTH_PASS_HASH', ''); // Kosong secara default: Mengaktifkan wizard pembuatan akun otomatis saat pertama kali dibuka di hosting

// Session Timeout (dalam detik). Default: 2592000 detik = 30 hari (Stay Logged In)
$sessionTimeoutVal = isset($customSettings['session_timeout']) ? (int)$customSettings['session_timeout'] : 2592000;
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 2592000);
}

// Rate Limiting Login (Maksimal percobaan gagal sebelum di-lockout)
define('MAX_LOGIN_ATTEMPTS', 10);
define('LOGIN_LOCKOUT_TIME', 300); // 5 menit

// --------------------------------------------------------------------------
// 4. STORAGE & SYSTEM PATHS
// --------------------------------------------------------------------------
define('STORAGE_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'storage');
define('LOG_FILE', STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'audit.log');
define('TEMP_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'temp');
define('LOGIN_ATTEMPTS_FILE', STORAGE_PATH . DIRECTORY_SEPARATOR . 'login_attempts.json');
define('CREDENTIALS_FILE', STORAGE_PATH . DIRECTORY_SEPARATOR . 'credentials.json');
define('SETTINGS_FILE', STORAGE_PATH . DIRECTORY_SEPARATOR . 'settings.json');

// --------------------------------------------------------------------------
// 5. UPLOAD SETTINGS
// --------------------------------------------------------------------------
// Batas maksimal upload file (dalam bytes). 0 = mengikuti batasan php.ini
$maxUploadVal = isset($customSettings['max_upload_size']) ? (int)$customSettings['max_upload_size'] : (200 * 1024 * 1024);
if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 209715200); // 200 MB
}

// File preview text extension whitelist
define('TEXT_PREVIEW_EXTENSIONS', [
    'txt', 'html', 'htm', 'css', 'js', 'json', 'xml', 'md', 'php',
    'sql', 'env', 'htaccess', 'ini', 'log', 'svg', 'sh', 'bat', 'cmd',
    'yml', 'yaml', 'conf'
]);

// --------------------------------------------------------------------------
// 6. DISK USAGE & QUOTA WIDGET
// --------------------------------------------------------------------------
/**
 * Pada server hosting bersama (Shared Hosting / cPanel), fungsi bawaan PHP
 * membaca total seluruh partisi harddisk fisik server pusat hosting (contoh: 2.84 TB),
 * bukan batas kuota paket hosting akun Anda.
 *
 * - Set false untuk MENYEMBUNYIKAN widget disk (Sangat disarankan pada shared hosting).
 * - Set true jika Anda menggunakan VPS / Dedicated Server atau ingin memantau disk server.
 * - Anda juga dapat menentukan kuota akun hosting Anda secara manual dalam MB pada
 *   DISK_QUOTA_MB (contoh: 5120 untuk kuota 5 GB, 10240 untuk 10 GB, 0 = ikuti partisi server).
 */
$showDiskVal = isset($customSettings['show_disk_usage']) ? (bool)$customSettings['show_disk_usage'] : false;
if (!defined('SHOW_DISK_USAGE')) {
    define('SHOW_DISK_USAGE', false); // Disembunyikan secara default agar tidak menampilkan kapasitas drive 2.8 TB server pusat
}

$diskQuotaVal = isset($customSettings['disk_quota_mb']) ? (int)$customSettings['disk_quota_mb'] : 0;
if (!defined('DISK_QUOTA_MB')) {
    define('DISK_QUOTA_MB', 0); // 0 = otomatis ikuti partisi, atau isi kuota paket hosting Anda dalam MB (contoh: 5120 = 5 GB)
}

