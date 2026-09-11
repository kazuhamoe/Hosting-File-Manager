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
$detectedRoot = __DIR__;
$parentDir = dirname(__DIR__);

if (@is_dir($parentDir) && @is_readable($parentDir)) {
    if (is_dir($parentDir . DIRECTORY_SEPARATOR . 'public_html')) {
        $detectedRoot = $parentDir;
    } elseif (basename($parentDir) === 'public_html') {
        $detectedRoot = $parentDir;
    }
}

define('ALLOWED_ROOT', $detectedRoot);

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
 * Kredensial login single-user (Username & Hash Password Bcrypt).
 * Catatan: Dapat diubah langsung melalui menu Pengaturan (⚙️) di antarmuka web.
 */
define('AUTH_USER', 'admin');
define('AUTH_PASS_HASH', '$2y$10$Soo6p/A.0wRiuxAxVjk7huyFt6rEGUWyrns8VHTgDdNYN0RDJ05Gi'); // Default: admin123

// Session Timeout (dalam detik). Default: 2592000 detik = 30 hari (Stay Logged In)
define('SESSION_TIMEOUT', 2592000);

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

// --------------------------------------------------------------------------
// 5. UPLOAD SETTINGS
// --------------------------------------------------------------------------
// Batas maksimal upload file (dalam bytes). 0 = mengikuti batasan php.ini
define('MAX_UPLOAD_SIZE', 200 * 1024 * 1024); // 200 MB

// File preview text extension whitelist
define('TEXT_PREVIEW_EXTENSIONS', [
    'txt', 'html', 'htm', 'css', 'js', 'json', 'xml', 'md', 'php',
    'sql', 'env', 'htaccess', 'ini', 'log', 'svg', 'sh', 'bat', 'cmd',
    'yml', 'yaml', 'conf'
]);
