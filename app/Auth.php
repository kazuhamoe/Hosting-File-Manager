<?php
if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access denied.');
}

/**
 * Authentication & Session Management
 * Menangani login, session timeout, CSRF, dan rate limiting.
 */

require_once __DIR__ . '/Logger.php';

class Auth
{
    /**
     * Mendapatkan IP address pengguna yang akurat (mendukung Cloudflare & Reverse Proxy).
     */
    public static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Inisialisasi sesi dengan parameter yang aman dan fallback direktori lokal.
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $lifetime = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 2592000;

            // Periksa dan amankan session.save_path agar tidak gagal di shared hosting / CloudLinux
            $savePath = @session_save_path();
            if (empty($savePath) || !@is_dir($savePath) || !@is_writable($savePath)) {
                $fallbackPath = defined('TEMP_PATH') ? TEMP_PATH : (__DIR__ . '/../storage/temp');
                if (!is_dir($fallbackPath)) {
                    @mkdir($fallbackPath, 0777, true);
                    @chmod($fallbackPath, 0777);
                }
                if (is_dir($fallbackPath) && is_writable($fallbackPath)) {
                    @session_save_path($fallbackPath);
                }
            }

            @ini_set('session.gc_maxlifetime', (string)$lifetime);
            @ini_set('session.cookie_lifetime', (string)$lifetime);
            @session_name('HFM_SESSID');

            if (!headers_sent()) {
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                           (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
                           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
                           (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false);

                if (PHP_VERSION_ID >= 70300) {
                    @session_set_cookie_params([
                        'lifetime' => $lifetime,
                        'path'     => '/',
                        'secure'   => $isHttps,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                } else {
                    @session_set_cookie_params($lifetime, '/', '', $isHttps, true);
                }
            }
            @session_start();
        }
    }

    /**
     * Mendapatkan kredensial tersimpan (dari storage/credentials.json atau config.php).
     */
    public static function getStoredCredentials(): array
    {
        $file = defined('CREDENTIALS_FILE') ? CREDENTIALS_FILE : (__DIR__ . '/../storage/credentials.json');
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content) {
                $data = json_decode($content, true);
                if (is_array($data) && !empty($data['username']) && !empty($data['password_hash'])) {
                    return [
                        'username' => $data['username'],
                        'password_hash' => $data['password_hash'],
                        'updated_at' => $data['updated_at'] ?? null
                    ];
                }
            }
        }

        return [
            'username' => defined('AUTH_USER') ? AUTH_USER : 'admin',
            'password_hash' => defined('AUTH_PASS_HASH') ? AUTH_PASS_HASH : '',
            'updated_at' => null
        ];
    }

    /**
     * Memeriksa apakah instalasi membutuhkan pembuatan akun administrator pertama kali (First-Time Setup).
     */
    public static function isSetupRequired(): bool
    {
        $file = defined('CREDENTIALS_FILE') ? CREDENTIALS_FILE : (__DIR__ . '/../storage/credentials.json');
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content) {
                $data = json_decode($content, true);
                if (is_array($data) && !empty($data['password_hash'])) {
                    return false;
                }
            }
        }

        // Jika config.php telah diisi hash secara eksplisit, setup otomatis di-skip
        if (defined('AUTH_PASS_HASH') && AUTH_PASS_HASH !== '') {
            return false;
        }

        return true;
    }

    /**
     * Membuat kredensial administrator awal saat pertama kali dipasang di hosting.
     */
    public static function setupInitialCredentials(string $username, string $password, string $confirmPassword = ''): array
    {
        if (!self::isSetupRequired()) {
            return ['success' => false, 'message' => 'Akun administrator sudah dikonfigurasi. Silakan login.'];
        }

        $username = trim($username);
        if (empty($username)) {
            $username = 'admin';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'message' => 'Username harus memiliki panjang 3-50 karakter.'];
        } elseif (!preg_match('/^[a-zA-Z0-9_\-\.@]+$/', $username)) {
            return ['success' => false, 'message' => 'Username hanya boleh mengandung huruf, angka, titik, underscore, strip, atau @.'];
        }

        $password = trim($password);
        if (strlen($password) < 5) {
            return ['success' => false, 'message' => 'Password baru minimal 5 karakter.'];
        }

        if ($confirmPassword !== '' && $password !== trim($confirmPassword)) {
            return ['success' => false, 'message' => 'Konfirmasi password baru tidak cocok.'];
        }

        $finalHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

        $file = defined('CREDENTIALS_FILE') ? CREDENTIALS_FILE : (__DIR__ . '/../storage/credentials.json');
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $dataToSave = [
            'username' => $username,
            'password_hash' => $finalHash,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'created_by_ip' => self::getClientIp()
        ];

        $saved = @file_put_contents($file, json_encode($dataToSave, JSON_PRETTY_PRINT), LOCK_EX);
        if ($saved === false) {
            return ['success' => false, 'message' => 'Gagal menyimpan kredensial. Pastikan folder storage/ memiliki izin tulis (chmod 0755/0777).'];
        }

        self::startSession();
        $_SESSION['hfm_logged_in'] = true;
        $_SESSION['hfm_username'] = $username;
        $_SESSION['hfm_last_activity'] = time();
        $_SESSION['hfm_csrf_token'] = bin2hex(random_bytes(32));

        Logger::log('SETUP', $username, 'SUCCESS', 'Inisialisasi akun administrator berhasil (First Run)');

        return [
            'success' => true,
            'message' => 'Akun administrator berhasil dibuat.',
            'username' => $username
        ];
    }

    /**
     * Memverifikasi apakah password saat ini cocok dengan kredensial aktif
     */
    public static function verifyPassword(string $password): bool
    {
        $creds = self::getStoredCredentials();
        $expectedHash = $creds['password_hash'];
        if (!empty($expectedHash) && password_verify($password, $expectedHash)) {
            return true;
        }
        return false;
    }

    /**
     * Memperbarui username dan/atau password admin secara dinamis dan aman.
     */
    public static function updateCredentials(string $currentPassword, string $newUsername, string $newPassword): array
    {
        self::startSession();

        if (!self::check()) {
            return ['success' => false, 'message' => 'Sesi tidak valid atau telah berakhir.'];
        }

        $creds = self::getStoredCredentials();
        $expectedHash = $creds['password_hash'];

        // 1. Verifikasi password saat ini
        $currentMatches = false;
        if (!empty($expectedHash) && password_verify($currentPassword, $expectedHash)) {
            $currentMatches = true;
        }

        if (!$currentMatches) {
            Logger::log('SETTINGS', $_SESSION['hfm_username'] ?? 'unknown', 'FAILED', 'Password saat ini salah');
            return ['success' => false, 'message' => 'Password saat ini salah. Perubahan dibatalkan.'];
        }

        // 2. Validasi username baru
        $newUsername = trim($newUsername);
        if (empty($newUsername)) {
            $newUsername = $creds['username'];
        } elseif (strlen($newUsername) < 3 || strlen($newUsername) > 50) {
            return ['success' => false, 'message' => 'Username harus memiliki panjang 3-50 karakter.'];
        } elseif (!preg_match('/^[a-zA-Z0-9_\-\.@]+$/', $newUsername)) {
            return ['success' => false, 'message' => 'Username hanya boleh mengandung huruf, angka, titik, underscore, strip, atau @.'];
        }

        // 3. Validasi password baru jika diisi
        $newPassword = trim($newPassword);
        $finalHash = $expectedHash;
        if ($newPassword !== '') {
            if (strlen($newPassword) < 5) {
                return ['success' => false, 'message' => 'Password baru minimal 5 karakter.'];
            }
            $finalHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
        }

        // 4. Simpan ke storage/credentials.json dengan file locking
        $file = defined('CREDENTIALS_FILE') ? CREDENTIALS_FILE : (__DIR__ . '/../storage/credentials.json');
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $dataToSave = [
            'username' => $newUsername,
            'password_hash' => $finalHash,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by_ip' => self::getClientIp()
        ];

        $saved = @file_put_contents($file, json_encode($dataToSave, JSON_PRETTY_PRINT), LOCK_EX);
        if ($saved === false) {
            return ['success' => false, 'message' => 'Gagal menyimpan kredensial. Pastikan folder storage/ memiliki izin tulis (chmod 0755/0777).'];
        }

        // Update session username aktif
        $_SESSION['hfm_username'] = $newUsername;
        Logger::log('SETTINGS', $newUsername, 'SUCCESS', 'Username / password berhasil diperbarui');

        return [
            'success' => true,
            'message' => 'Pengaturan akun berhasil disimpan.',
            'username' => $newUsername
        ];
    }

    /**
     * Memeriksa apakah user sedang login dan sesi belum kedaluwarsa.
     */
    public static function check(): bool
    {
        self::startSession();

        if (empty($_SESSION['hfm_logged_in']) || $_SESSION['hfm_logged_in'] !== true) {
            return false;
        }

        // Pengecekan session timeout (Default 30 hari)
        $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 2592000;
        if (isset($_SESSION['hfm_last_activity']) && (time() - $_SESSION['hfm_last_activity'] > $timeout)) {
            self::logout();
            return false;
        }

        // Perbarui waktu aktivitas terakhir
        $_SESSION['hfm_last_activity'] = time();
        return true;
    }

    /**
     * Mencoba login user dengan rate limiting dan toleransi default credentials.
     */
    public static function login(string $username, string $password): array
    {
        self::startSession();

        $ip = self::getClientIp();

        // Periksa rate limiting
        if (self::isLockedOut($ip)) {
            Logger::log('LOGIN', $username, 'BLOCKED', 'Rate limit exceeded for IP ' . $ip);
            return [
                'success' => false,
                'message' => 'Terlalu banyak percobaan gagal (Lockout aktif). Hapus file storage/login_attempts.json via cPanel untuk membuka blokir seketika.'
            ];
        }

        $creds = self::getStoredCredentials();
        $expectedUser = $creds['username'];
        $expectedHash = $creds['password_hash'];

        // Case-insensitive username agar 'admin' atau 'Admin' diterima
        $userMatches = strcasecmp($expectedUser, $username) === 0;

        // Pengecekan password via Bcrypt hash
        $passMatches = false;
        if (!empty($expectedHash) && password_verify($password, $expectedHash)) {
            $passMatches = true;
        }

        if ($userMatches && $passMatches) {
            // Reset counter percobaan gagal
            self::resetAttempts($ip);

            $_SESSION['hfm_logged_in'] = true;
            $_SESSION['hfm_username'] = $creds['username'];
            $_SESSION['hfm_last_activity'] = time();
            $_SESSION['hfm_csrf_token'] = bin2hex(random_bytes(32));

            Logger::log('LOGIN', $creds['username'], 'SUCCESS', 'IP ' . $ip);

            return [
                'success' => true,
                'message' => 'Login berhasil.',
                'csrf_token' => $_SESSION['hfm_csrf_token']
            ];
        }

        // Catat kegagalan
        self::recordFailedAttempt($ip);
        Logger::log('LOGIN', $username, 'FAILED', 'Invalid credentials from IP ' . $ip);

        return [
            'success' => false,
            'message' => 'Username atau password salah.'
        ];
    }

    /**
     * Melakukan logout dan membersihkan session.
     */
    public static function logout(): void
    {
        self::startSession();
        $user = $_SESSION['hfm_username'] ?? 'unknown';
        Logger::log('LOGOUT', $user, 'SUCCESS');

        $_SESSION = [];

        if (!headers_sent() && ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            @setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
    }

    /**
     * Mendapatkan atau membuat CSRF token.
     */
    public static function getCsrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['hfm_csrf_token'])) {
            $_SESSION['hfm_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['hfm_csrf_token'];
    }

    /**
     * Memvalidasi CSRF token dari request POST / header.
     */
    public static function validateCsrfToken(?string $token): bool
    {
        self::startSession();
        $sessionToken = $_SESSION['hfm_csrf_token'] ?? '';
        if ($sessionToken === '' || $token === null || $token === '') {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }

    // ----------------------------------------------------------------------
    // RATE LIMITING IMPLEMENTATION (Menggunakan file JSON aman di storage)
    // ----------------------------------------------------------------------

    private static function getAttemptsData(): array
    {
        $file = defined('LOGIN_ATTEMPTS_FILE') ? LOGIN_ATTEMPTS_FILE : (__DIR__ . '/../storage/login_attempts.json');
        if (!file_exists($file)) {
            return [];
        }
        $content = @file_get_contents($file);
        if (!$content) {
            return [];
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private static function saveAttemptsData(array $data): void
    {
        $file = defined('LOGIN_ATTEMPTS_FILE') ? LOGIN_ATTEMPTS_FILE : (__DIR__ . '/../storage/login_attempts.json');
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private static function isLockedOut(string $ip): bool
    {
        $data = self::getAttemptsData();
        if (!isset($data[$ip])) {
            return false;
        }

        $entry = $data[$ip];
        $maxAttempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;
        $lockoutTime = defined('LOGIN_LOCKOUT_TIME') ? LOGIN_LOCKOUT_TIME : 900;

        if ($entry['attempts'] >= $maxAttempts) {
            if (time() - $entry['last_time'] < $lockoutTime) {
                return true;
            }
            // Masa lockout berakhir, reset
            unset($data[$ip]);
            self::saveAttemptsData($data);
        }

        return false;
    }

    private static function recordFailedAttempt(string $ip): void
    {
        $data = self::getAttemptsData();
        if (!isset($data[$ip])) {
            $data[$ip] = ['attempts' => 1, 'last_time' => time()];
        } else {
            $data[$ip]['attempts']++;
            $data[$ip]['last_time'] = time();
        }
        self::saveAttemptsData($data);
    }

    private static function resetAttempts(string $ip): void
    {
        $data = self::getAttemptsData();
        if (isset($data[$ip])) {
            unset($data[$ip]);
            self::saveAttemptsData($data);
        }
    }
}
