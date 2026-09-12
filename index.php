<?php
// Output buffering immediately to prevent any accidental output/warnings from breaking JSON
ob_start();

// Error reporting and exception handling to prevent silent HTTP 500 crashes
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0');

set_exception_handler(function ($e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || 
              (isset($_GET['action']) && $_GET['action'] !== '') ||
              (isset($_POST['action']) && $_POST['action'] !== '');
              
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Server Exception: ' . $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Hosting File Manager - System Notice</title>';
    echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;box-sizing:border-box;}';
    echo '.err-card{background:#1e293b;border:1px solid #ef4444;border-radius:8px;padding:24px;max-width:540px;width:100%;box-shadow:0 10px 25px rgba(0,0,0,0.4);}';
    echo 'h2{color:#f87171;margin-top:0;font-size:18px;} p{color:#cbd5e1;font-size:13px;line-height:1.6;} code{background:#0f172a;padding:2px 6px;border-radius:4px;color:#38bdf8;font-size:12px;}';
    echo '.btn-retry{display:inline-block;background:#0284c7;color:#fff;text-decoration:none;padding:8px 16px;border-radius:4px;font-size:13px;margin-top:12px;font-weight:500;}</style></head>';
    echo '<body><div class="err-card"><h2>Pemberitahuan Sistem (HTTP 500)</h2>';
    echo '<p>Pesan: <strong>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</strong></p>';
    echo '<p>Lokasi: <code>' . htmlspecialchars(basename($e->getFile()), ENT_QUOTES, 'UTF-8') . ':' . $e->getLine() . '</code></p>';
    echo '<p style="color:#94a3b8;font-size:12px;">Pastikan folder <code>storage/</code> memiliki izin tulis (chmod 0755 / 0777) dan path <code>ALLOWED_ROOT</code> di <code>config.php</code> dapat diakses.</p>';
    echo '<a href="./" class="btn-retry">Muat Ulang Halaman</a></div></body></html>';
    exit;
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
        }
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || 
                  (isset($_GET['action']) && $_GET['action'] !== '') ||
                  (isset($_POST['action']) && $_POST['action'] !== '');
                  
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Fatal Error: ' . $error['message'],
                'file' => basename($error['file']),
                'line' => $error['line']
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }
        
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Hosting File Manager - System Notice</title>';
        echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;box-sizing:border-box;}';
        echo '.err-card{background:#1e293b;border:1px solid #ef4444;border-radius:8px;padding:24px;max-width:540px;width:100%;box-shadow:0 10px 25px rgba(0,0,0,0.4);}';
        echo 'h2{color:#f87171;margin-top:0;font-size:18px;} p{color:#cbd5e1;font-size:13px;line-height:1.6;} code{background:#0f172a;padding:2px 6px;border-radius:4px;color:#38bdf8;font-size:12px;}';
        echo '.btn-retry{display:inline-block;background:#0284c7;color:#fff;text-decoration:none;padding:8px 16px;border-radius:4px;font-size:13px;margin-top:12px;font-weight:500;}</style></head>';
        echo '<body><div class="err-card"><h2>Pemberitahuan Sistem (Fatal Error)</h2>';
        echo '<p>Pesan: <strong>' . htmlspecialchars($error['message'], ENT_QUOTES, 'UTF-8') . '</strong></p>';
        echo '<p>Lokasi: <code>' . htmlspecialchars(basename($error['file']), ENT_QUOTES, 'UTF-8') . ':' . $error['line'] . '</code></p>';
        echo '<p style="color:#94a3b8;font-size:12px;">Pastikan versi PHP hosting minimal PHP 7.4 (direkomendasikan PHP 8.1+) dan ekstensi zip/mbstring aktif.</p>';
        echo '<a href="./" class="btn-retry">Muat Ulang Halaman</a></div></body></html>';
        exit;
    }
});

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
 * Hosting File Manager & ZIP Extractor
 * Entry Point Utama Aplikasi
 */

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Header Keamanan
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Security.php';
require_once __DIR__ . '/app/Logger.php';
require_once __DIR__ . '/app/Auth.php';
require_once __DIR__ . '/app/FileManager.php';
require_once __DIR__ . '/app/ZipManager.php';

Auth::startSession();

// Helper untuk respons JSON API murni
function jsonResponse(array $data, int $statusCode = 200): void
{
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// --------------------------------------------------------------------------
// PROSES AUTENTIKASI (LOGIN, SETUP AWAL, & LOGOUT)
// --------------------------------------------------------------------------
$isSetupRequired = Auth::isSetupRequired();

// Proses Wizard Pembuatan Akun Administrator Pertama Kali (Create Password)
if ($isSetupRequired && $action === 'setup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $result = Auth::setupInitialCredentials($username, $password, $confirmPassword);

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        jsonResponse($result, $result['success'] ? 200 : 400);
    }

    if ($result['success']) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Location: ./');
        exit;
    }

    $setupError = $result['message'];
}

// Proses Login Biasa
if (!$isSetupRequired && $action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $result = Auth::login($username, $password);

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        jsonResponse($result, $result['success'] ? 200 : 401);
    }

    if ($result['success']) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Location: ./');
        exit;
    }

    $loginError = $result['message'];
}

if ($action === 'logout') {
    Auth::logout();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Location: ./');
    exit;
}

// --------------------------------------------------------------------------
// PROTEKSI AKSES: TAMPILKAN FORM SETUP ATAU LOGIN JIKA BELUM TERAUTENTIKASI
// --------------------------------------------------------------------------
if (!Auth::check()) {
    // Jika request berupa API/AJAX, tolak dengan 401
    if (!empty($action) && $action !== 'login' && $action !== 'setup') {
        jsonResponse(['success' => false, 'message' => 'Sesi kedaluwarsa atau belum terautentikasi.'], 401);
    }

    if ($isSetupRequired) {
        // TAMPILAN 1: WIZARD FIRST-TIME SETUP (CREATE PASSWORD)
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Setup Administrator — Hosting File Manager</title>
            <link rel="stylesheet" href="assets/css/style.css">
        </head>
        <body>
            <div class="login-wrapper">
                <div class="login-card" style="max-width: 420px;">
                    <div class="login-header">
                        <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                        <div>
                            <h1>Setup Administrator</h1>
                            <p>Inisialisasi Pertama Hosting File Manager</p>
                        </div>
                    </div>
                    <div class="login-body">
                        <div class="setup-welcome-banner" style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 10px 12px; margin-bottom: 16px; font-size: 12px; color: #0369a1; line-height: 1.5;">
                            <strong>👋 Selamat Datang!</strong>
                            <div style="margin-top: 2px;">File Manager baru saja dipasang di server hosting ini. Silakan buat username dan password administrator Anda untuk memulai.</div>
                        </div>

                        <?php if (!empty($setupError)): ?>
                            <div class="alert alert-danger" style="margin-bottom: 16px; font-size: 13px;">
                                <span>✕</span>
                                <span><?= htmlspecialchars($setupError, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="?action=setup">
                            <input type="hidden" name="action" value="setup">
                            <div class="form-group">
                                <label for="setupUsername">Username Admin</label>
                                <input type="text" id="setupUsername" name="username" class="form-control" required autofocus autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Masukkan username">
                                <small style="color: #64748b; font-size: 11px; display: block; margin-top: 3px;">Minimal 3 karakter (huruf, angka, ., _, -).</small>
                            </div>
                            <div class="form-group">
                                <label for="setupPassword">Password Baru</label>
                                <input type="password" id="setupPassword" name="password" class="form-control" required autocomplete="new-password" placeholder="Minimal 5 karakter" minlength="5">
                            </div>
                            <div class="form-group">
                                <label for="setupConfirmPassword">Ulangi Password Baru</label>
                                <input type="password" id="setupConfirmPassword" name="confirm_password" class="form-control" required autocomplete="new-password" placeholder="Ketik ulang password">
                            </div>
                            <button type="submit" class="btn btn-primary btn-block" style="padding: 9px; font-weight: 600; margin-top: 6px;">
                                ✓ Buat Akun &amp; Masuk ke File Manager
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    // TAMPILAN 2: HALAMAN LOGIN STANDAR (SETELAH AKUN DIBUAT)
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login — Hosting File Manager</title>
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
        <div class="login-wrapper">
            <div class="login-card">
                <div class="login-header">
                    <svg viewBox="0 0 24 24"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM19 18H6c-2.21 0-4-1.79-4-4 0-2.05 1.53-3.76 3.56-3.97l1.07-.11.5-.95C8.08 7.14 9.94 6 12 6c2.62 0 4.88 1.86 5.39 4.43l.3 1.5 1.53.11c1.56.1 2.78 1.41 2.78 2.96 0 1.65-1.35 3-3 3z"/></svg>
                    <div>
                        <h1>Hosting File Manager</h1>
                        <p>Autentikasi Server Hosting</p>
                    </div>
                </div>
                <div class="login-body">
                    <?php if (!empty($loginError)): ?>
                        <div class="alert alert-danger" style="margin-bottom: 16px; font-size: 13px;">
                            <span>✕</span>
                            <span><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="?action=login">
                        <input type="hidden" name="action" value="login">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" required autofocus autocomplete="username" placeholder="Masukkan username">
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password" placeholder="Masukkan password">
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" style="padding: 9px;">Masuk ke File Manager</button>
                    </form>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --------------------------------------------------------------------------
// ROUTING API ACTIONS (SETELAH TERAUTENTIKASI)
// --------------------------------------------------------------------------
$csrfToken = Auth::getCsrfToken();

// Download File Langsung
if ($action === 'download') {
    $filePath = $_GET['path'] ?? '';
    FileManager::downloadFile($filePath);
    exit;
}

// Download Folder sebagai ZIP
if ($action === 'download_folder') {
    $folderPath = $_GET['path'] ?? '';
    FileManager::downloadFolderAsZip($folderPath);
    exit;
}

// Download Banyak Item sebagai ZIP (Bulk Download)
if ($action === 'bulk_download') {
    $paths = $_GET['paths'] ?? [];
    if (is_string($paths)) {
        $decoded = json_decode($paths, true);
        if (is_array($decoded)) {
            $paths = $decoded;
        } else {
            $paths = explode(',', $paths);
        }
    }
    $name = $_GET['name'] ?? 'download.zip';
    FileManager::downloadMultipleAsZip((array)$paths, $name);
    exit;
}

// Diagnostic Batas Upload Server (Safe JSON)
if ($action === 'upload_limits') {
    jsonResponse([
        'success' => true,
        'limits' => Security::getServerUploadLimits()
    ]);
}

// Cek Keberadaan Berkas di Server (Mencegah Duplikasi / False Failure)
if ($action === 'check_file') {
    $dir = $_GET['dir'] ?? ($_POST['dir'] ?? '/');
    $name = trim($_GET['name'] ?? ($_POST['name'] ?? ''));
    $expectedSize = isset($_GET['size']) ? (int)$_GET['size'] : (isset($_POST['size']) ? (int)$_POST['size'] : -1);

    $resolvedDir = Security::resolvePath($dir, true);
    if ($resolvedDir === null || !is_dir($resolvedDir) || empty($name)) {
        jsonResponse(['success' => false, 'exists' => false, 'message' => 'Parameter direktori atau nama file tidak valid.'], 400);
    }

    $sanitized = Security::sanitizeFilename($name);
    $targetPath = $resolvedDir . DIRECTORY_SEPARATOR . $sanitized;

    if (file_exists($targetPath) && is_file($targetPath)) {
        $actualSize = filesize($targetPath);
        $matches = ($expectedSize < 0) || ($expectedSize === $actualSize);
        jsonResponse([
            'success' => true,
            'exists' => true,
            'name' => $sanitized,
            'size' => $actualSize,
            'size_human' => Security::formatBytes($actualSize),
            'mtime' => filemtime($targetPath),
            'matches_size' => $matches
        ]);
    } else {
        jsonResponse([
            'success' => true,
            'exists' => false,
            'name' => $sanitized
        ]);
    }
}

// Deteksi Payload Over-Capacity (post_max_size overflow)
// Ketika ukuran request melebihi post_max_size php.ini, PHP mengosongkan $_POST dan $_FILES serta menghasilkan warning.
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && $contentLength > 0) {
    $limits = Security::getServerUploadLimits();
    $receivedHuman = Security::formatBytes($contentLength);
    $limitHuman = Security::formatBytes($limits['post_max_size_bytes']);
    jsonResponse([
        'success' => false,
        'message' => "Ukuran berkas yang diunggah ($receivedHuman) melebihi batas 'post_max_size' konfigurasi server ($limitHuman). Harap tingkatkan nilai post_max_size dan upload_max_filesize pada php.ini hosting.",
        'error_code' => 'POST_MAX_SIZE_EXCEEDED',
        'content_length' => $contentLength,
        'content_length_human' => $receivedHuman,
        'server_limits' => [
            'post_max_size' => $limits['post_max_size'],
            'upload_max_filesize' => $limits['upload_max_filesize'],
            'memory_limit' => $limits['memory_limit']
        ]
    ], 413);
}

// Penanganan Request Mutasi (POST) dengan Validasi CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!Auth::validateCsrfToken($submittedToken)) {
        jsonResponse(['success' => false, 'message' => 'Validasi CSRF Token gagal. Silakan muat ulang halaman.'], 403);
    }

    switch ($action) {
        case 'upload':
            $targetDir = $_POST['target_dir'] ?? ($_GET['target_dir'] ?? ($_SERVER['HTTP_X_TARGET_DIR'] ?? '/'));
            $realTargetDir = Security::resolvePath($targetDir, true);

            if ($realTargetDir === null || !is_dir($realTargetDir)) {
                jsonResponse(['success' => false, 'message' => 'Direktori tujuan upload tidak valid atau akses ditolak.'], 400);
            }

            // Ekstraksi seluruh file dari $_FILES secara universal (baik 'file', 'files', 'files[]', dll.)
            $filesList = [];
            $rawErrors = [];
            foreach ($_FILES as $field => $data) {
                if (isset($data['name']) && is_array($data['name'])) {
                    for ($i = 0; $i < count($data['name']); $i++) {
                        if (!empty($data['name'][$i])) {
                            $filesList[] = [
                                'name' => $data['name'][$i],
                                'tmp_name' => $data['tmp_name'][$i],
                                'error' => $data['error'][$i],
                                'size' => $data['size'][$i],
                            ];
                        } elseif (isset($data['error'][$i]) && $data['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                            $rawErrors[] = $data['error'][$i];
                        }
                    }
                } elseif (isset($data['name']) && !empty($data['name'])) {
                    $filesList[] = [
                        'name' => $data['name'],
                        'tmp_name' => $data['tmp_name'],
                        'error' => $data['error'],
                        'size' => $data['size'],
                    ];
                } elseif (isset($data['error']) && $data['error'] !== UPLOAD_ERR_NO_FILE) {
                    $rawErrors[] = $data['error'];
                }
            }

            if (empty($filesList)) {
                $limits = Security::getServerUploadLimits();
                $detail = "Batas upload server: upload_max_filesize={$limits['upload_max_filesize']}, post_max_size={$limits['post_max_size']}.";
                if (!empty($rawErrors)) {
                    $errCode = $rawErrors[0];
                    if ($errCode === UPLOAD_ERR_INI_SIZE) {
                        jsonResponse([
                            'success' => false,
                            'message' => "Ukuran berkas melebihi batas upload_max_filesize server ({$limits['upload_max_filesize']}). $detail",
                            'error_code' => 'UPLOAD_ERR_INI_SIZE'
                        ], 400);
                    }
                }
                jsonResponse([
                    'success' => false,
                    'message' => "Tidak ada file yang berhasil diterima oleh server. Pastikan ukuran file tidak melebihi batas konfigurasi server ($detail).",
                    'limits' => $limits
                ], 400);
            }

            $uploadedCount = 0;
            $failedFiles = [];
            $uploadedItems = [];

            foreach ($filesList as $f) {
                $res = FileManager::saveUploadedFile($targetDir, $f['tmp_name'], $f['name'], $f['size'], $f['error'], true);
                if ($res['success']) {
                    $uploadedCount++;
                    $uploadedItems[] = $res;
                } else {
                    $failedFiles[] = $f['name'] . ' (' . ($res['message'] ?? 'Gagal') . ')';
                }
            }

            if ($uploadedCount === 0) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Gagal mengunggah file: ' . implode(', ', $failedFiles),
                    'failed_files' => $failedFiles
                ], 400);
            }

            $msg = "$uploadedCount file berhasil diunggah.";
            if (!empty($failedFiles)) {
                $msg .= ' Beberapa file gagal: ' . implode(', ', $failedFiles);
            }

            jsonResponse([
                'success' => true,
                'message' => $msg,
                'uploaded_count' => $uploadedCount,
                'target_dir' => $targetDir,
                'files' => $uploadedItems,
                'failed_files' => $failedFiles
            ]);

        case 'create_file':
            $targetDir = $_POST['target_dir'] ?? '/';
            $fileName = $_POST['file_name'] ?? '';
            $content = $_POST['content'] ?? '';
            jsonResponse(FileManager::createFile($targetDir, $fileName, $content));

        case 'mkdir':
            $targetDir = $_POST['target_dir'] ?? '/';
            $folderName = $_POST['folder_name'] ?? '';
            jsonResponse(FileManager::createFolder($targetDir, $folderName));

        case 'rename':
            $path = $_POST['path'] ?? '';
            $newName = $_POST['new_name'] ?? '';
            jsonResponse(FileManager::renameItem($path, $newName));

        case 'delete':
            $path = $_POST['path'] ?? '';
            jsonResponse(FileManager::deleteItem($path));

        case 'copy':
            $source = $_POST['source'] ?? '';
            $destination = $_POST['destination'] ?? '/';
            jsonResponse(FileManager::copyItem($source, $destination));

        case 'move':
            $source = $_POST['source'] ?? '';
            $destination = $_POST['destination'] ?? '/';
            jsonResponse(FileManager::moveItem($source, $destination));

        case 'extract':
            $zipPath = $_POST['zip_path'] ?? '';
            $destPath = $_POST['dest_path'] ?? '/';
            $conflict = $_POST['conflict'] ?? 'overwrite';
            jsonResponse(ZipManager::extractZip($zipPath, $destPath, $conflict));

        case 'compress':
            $items = $_POST['items'] ?? [];
            if (is_string($items)) {
                $items = json_decode($items, true) ?: [$items];
            }
            $destDir = $_POST['destination'] ?? '/';
            $zipName = $_POST['zip_name'] ?? 'archive.zip';
            jsonResponse(ZipManager::createZip((array)$items, $destDir, $zipName));

        case 'chmod':
            $path = $_POST['path'] ?? '';
            $mode = $_POST['mode'] ?? '0755';
            jsonResponse(FileManager::changePermissions($path, $mode));

        case 'edit_save':
            $path = $_POST['path'] ?? '';
            $content = $_POST['content'] ?? '';
            jsonResponse(FileManager::saveFileContent($path, $content));

        case 'bulk_delete':
            $paths = $_POST['paths'] ?? [];
            if (is_string($paths)) {
                $paths = json_decode($paths, true) ?: [$paths];
            }
            jsonResponse(FileManager::bulkDelete((array)$paths));

        case 'bulk_copy':
            $paths = $_POST['paths'] ?? [];
            if (is_string($paths)) {
                $paths = json_decode($paths, true) ?: [$paths];
            }
            $destination = $_POST['destination'] ?? '/';
            jsonResponse(FileManager::bulkCopy((array)$paths, $destination));

        case 'bulk_move':
            $paths = $_POST['paths'] ?? [];
            if (is_string($paths)) {
                $paths = json_decode($paths, true) ?: [$paths];
            }
            $destination = $_POST['destination'] ?? '/';
            jsonResponse(FileManager::bulkMove((array)$paths, $destination));

        case 'update_settings':
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newUsername = trim((string)($_POST['new_username'] ?? ''));
            $newPassword = (string)($_POST['new_password'] ?? '');
            jsonResponse(Auth::updateCredentials($currentPassword, $newUsername, $newPassword));

        case 'duplicate':
            $path = (string)($_POST['path'] ?? '');
            jsonResponse(FileManager::duplicateItem($path));

        default:
            jsonResponse(['success' => false, 'message' => 'Aksi POST tidak dikenal.'], 400);
    }
}

// Penanganan Request Baca (GET AJAX)
if (!empty($action)) {
    switch ($action) {
        case 'get_settings':
            $creds = Auth::getStoredCredentials();
            jsonResponse([
                'success' => true,
                'username' => $creds['username'] ?? 'admin',
                'updated_at' => $creds['updated_at'] ?? null
            ]);

        case 'list':
            $path = $_GET['path'] ?? '/';
            $sort = $_GET['sort'] ?? 'name';
            $order = $_GET['order'] ?? 'asc';
            $showHidden = isset($_GET['show_hidden']) && ($_GET['show_hidden'] === '1' || $_GET['show_hidden'] === 'true');
            $listing = FileManager::listDirectory($path, $sort, $order, $showHidden);
            $listing['disk'] = FileManager::getDiskUsage();
            jsonResponse($listing);

        case 'disk_usage':
            jsonResponse(FileManager::getDiskUsage());

        case 'folder_tree':
            jsonResponse(['success' => true, 'tree' => FileManager::getFolderTree()]);

        case 'folder_stats':
            $path = $_GET['path'] ?? '';
            jsonResponse(FileManager::getFolderStats($path));

        case 'info':
            $path = $_GET['path'] ?? '';
            jsonResponse(FileManager::getItemInfo($path));

        case 'preview':
            $path = $_GET['path'] ?? '';
            jsonResponse(FileManager::getFilePreview($path));

        case 'edit_load':
            $path = $_GET['path'] ?? '';
            jsonResponse(FileManager::getFileContent($path));

        default:
            jsonResponse(['success' => false, 'message' => 'Aksi GET tidak dikenal.'], 400);
    }
}

// --------------------------------------------------------------------------
// RENDER ANTARMUKA FILE MANAGER UTAMA
// --------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <?php
    $cssVer = (string)(@filemtime(__DIR__ . '/assets/css/style.css') ?: time());
    $jsVer  = (string)(@filemtime(__DIR__ . '/assets/js/app.js') ?: time());
    ?>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= $cssVer ?>">
    <style>
        .btn-settings {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #93c5fd !important;
            border: 1px solid #1e40af !important;
            background: rgba(30, 64, 175, 0.35) !important;
            padding: 3px 10px !important;
            border-radius: 4px !important;
            font-size: 11.5px !important;
            cursor: pointer !important;
            text-decoration: none !important;
            font-family: inherit !important;
            font-weight: 500 !important;
            line-height: 1.2 !important;
            transition: all 0.15s ease;
        }
        .btn-settings:hover {
            background: #1d4ed8 !important;
            color: #ffffff !important;
            border-color: #2563eb !important;
        }
        #modalSettings.show {
            display: flex !important;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Top Navbar -->
        <header class="app-header">
            <div class="header-brand">
                <svg viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                <span>Hosting File Manager</span>
            </div>
            <div class="header-user">
                <!-- Disk Usage / Quota Meter Widget -->
                <div class="disk-meter" id="diskMeter" title="Kapasitas Disk Server">
                    <div class="disk-meter-info">
                        <svg class="disk-meter-svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                        <span id="diskMeterText">Disk: -- / --</span>
                    </div>
                    <div class="disk-meter-track">
                        <div class="disk-meter-bar" id="diskMeterBar" style="width: 0%;"></div>
                    </div>
                </div>

                <!-- Theme Toggle Button -->
                <button type="button" id="btnThemeToggle" class="btn-theme-toggle" title="Beralih Mode Gelap / Terang (Dark / Light)">
                    <svg id="iconThemeMoon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg id="iconThemeSun" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                </button>

                <span class="user-badge" id="headerUserBadge">User: <?= htmlspecialchars($_SESSION['hfm_username'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?></span>
                <button type="button" id="btnOpenSettings" class="btn-settings" onclick="openSettingsModalFallback()" title="Pengaturan Akun Admin">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <span>Pengaturan</span>
                </button>
                <a href="?action=logout" class="btn-logout">Logout</a>
            </div>
        </header>

        <!-- Action Toolbar -->
        <div class="app-toolbar">
            <div class="toolbar-group">
                <!-- Navigation -->
                <button id="btnBack" class="btn btn-secondary btn-sm" title="Kembali (Back)">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                </button>
                <button id="btnForward" class="btn btn-secondary btn-sm" title="Maju (Forward)">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
                <button id="btnUp" class="btn btn-secondary btn-sm" title="Folder Di Atasnya (Up)">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                </button>
                <button id="btnReload" class="btn btn-secondary btn-sm" title="Muat Ulang (Reload)">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                </button>
                
                <span class="toolbar-sep">|</span>

                <!-- Create & Upload -->
                <button id="btnNewFolder" class="btn btn-secondary btn-sm" title="Buat Folder Baru">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
                    <span>Folder</span>
                </button>
                <button id="btnNewFile" class="btn btn-secondary btn-sm" title="Buat Berkas Baru">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                    <span>File</span>
                </button>
                <button id="btnOpenUpload" class="btn btn-primary btn-sm btn-upload-highlight" title="Unggah Berkas (Upload)">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span>Upload</span>
                </button>

                <span class="toolbar-sep">|</span>

                <!-- Selection Actions (Enabled dynamically) -->
                <button id="btnDownloadSelected" class="btn btn-secondary btn-sm toolbar-sel-btn" disabled title="Unduh Item Terpilih">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Download</span>
                </button>
                <button id="btnCompressSelected" class="btn btn-secondary btn-sm toolbar-sel-btn" disabled title="Kompres Item Terpilih ke ZIP">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                    <span>Compress</span>
                </button>
                <button id="btnExtractSelected" class="btn btn-secondary btn-sm toolbar-sel-btn" disabled title="Ekstrak Arsip ZIP">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/><line x1="12" y1="11" x2="12" y2="17"/><polyline points="9 14 12 11 15 14"/></svg>
                    <span>Extract</span>
                </button>
                <button id="btnCopySelected" class="btn btn-secondary btn-sm toolbar-sel-btn" disabled title="Salin Item Terpilih">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    <span>Copy</span>
                </button>
                <button id="btnDuplicateSelected" class="btn btn-secondary btn-sm toolbar-sel-btn" disabled title="Duplikat Item Terpilih (1-Click Duplicate)">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                    <span>Duplicate</span>
                </button>
                <button id="btnMoveSelected" class="btn btn-secondary btn-sm toolbar-sel-btn" disabled title="Pindahkan Item Terpilih">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg>
                    <span>Move</span>
                </button>
                <button id="btnEditSelected" class="btn btn-secondary btn-sm toolbar-sel-btn" disabled title="Edit Berkas Teks">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    <span>Edit</span>
                </button>
                <button id="btnRenameSelected" class="btn btn-secondary btn-sm toolbar-sel-btn" disabled title="Ganti Nama">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    <span>Rename</span>
                </button>
                <button id="btnChmodSelected" class="btn btn-secondary btn-sm toolbar-sel-btn" disabled title="Ubah Hak Akses (Permissions)">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    <span>Permissions</span>
                </button>
                <button id="btnDeleteSelected" class="btn btn-danger btn-sm toolbar-sel-btn" disabled title="Hapus Item Terpilih">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    <span>Delete</span>
                </button>

                <span class="toolbar-sep">|</span>

                <!-- View Toggle -->
                <button id="btnToggleHidden" class="btn btn-secondary btn-sm" title="Tampilkan/Sembunyikan Berkas Tersembunyi (dotfiles)">
                    <svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <span>Hidden</span>
                </button>
            </div>
            
            <div class="toolbar-search">
                <svg class="search-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchInput" placeholder="Cari file..." autocomplete="off">
                <button type="button" id="btnSearchClear" class="search-clear-btn" title="Bersihkan pencarian" style="display: none;">&times;</button>
            </div>
        </div>

        <!-- Location & Breadcrumb Navigation -->
        <div class="app-location-bar">
            <div class="location-label">
                <svg viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                <span>Current Directory:</span>
            </div>
            <nav class="app-breadcrumb" id="breadcrumbsContainer">
                <span class="breadcrumb-item active">Root</span>
            </nav>
        </div>

        <!-- Main Content Table -->
        <main class="app-main">
            <div class="file-table-container">
                <table class="file-table" id="fileTable">
                    <thead>
                        <tr>
                            <th style="width: 38px; text-align: center;" class="no-sort">
                                <input type="checkbox" id="selectAllCheckbox" title="Pilih Semua (Select All)">
                            </th>
                            <th data-sort="name">Name</th>
                            <th data-sort="type">Type</th>
                            <th data-sort="size">Size</th>
                            <th data-sort="modified">Modified</th>
                            <th class="no-sort">Permissions</th>
                            <th class="no-sort" style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="fileTableBody">
                        <!-- Diisi secara dinamis oleh app.js -->
                    </tbody>
                </table>
            </div>

            <!-- Status Bar Bawah -->
            <div class="app-status-bar">
                <div class="status-info">
                    <span id="statusTotalItems">0 item</span>
                    <span class="status-separator">|</span>
                    <span id="statusSelectedItems">0 item dipilih</span>
                    <span class="status-separator">|</span>
                    <span id="statusHiddenHint">Berkas tersembunyi: Disembunyikan</span>
                </div>
                <div class="status-extra">
                    <span id="statusCurrentPathHint">/</span>
                </div>
            </div>
        </main>
    </div>

    <!-- Toast Notifications Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- ====================================================================
         MODALS
         ==================================================================== -->

    <!-- Modal: Upload Files -->
    <div class="modal-backdrop" id="modalUpload">
        <div class="modal-box modal-lg">
            <div class="modal-header">
                <h3>Unggah Berkas (Upload Files)</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Target Directory Indicator -->
                <div class="upload-target-banner">
                    <span class="upload-target-label">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                        Lokasi Tujuan (Target Folder):
                    </span>
                    <code id="uploadTargetDirDisplay" class="upload-target-path">/</code>
                </div>

                <!-- Server Diagnostic Limits Bar -->
                <div class="upload-limits-info" id="uploadLimitsBar" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 12px; margin-bottom: 12px; font-size: 11.5px; color: #475569; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
                    <div>
                        <span style="font-weight: 600;">Batas Konfigurasi Server:</span>
                        <span>upload_max_filesize: <code id="lblUploadMaxFilesize" style="font-weight: 600; color: #0284c7;">--</code></span> |
                        <span>post_max_size: <code id="lblPostMaxSize" style="font-weight: 600; color: #0284c7;">--</code></span> |
                        <span>memory_limit: <code id="lblMemoryLimit">--</code></span>
                    </div>
                    <div id="lblUploadLimitStatus"></div>
                </div>

                <!-- Dropzone Box -->
                <div class="upload-dropzone" id="uploadDropArea">
                    <div class="dropzone-icon">
                        <svg viewBox="0 0 24 24" width="44" height="44" fill="#0066cc">
                            <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/>
                        </svg>
                    </div>
                    <div class="dropzone-title">Drag &amp; Drop berkas ke area ini</div>
                    <div class="dropzone-subtitle">Mendukung berkas apa pun: ZIP, PHP, HTML, CSS, JS, Gambar, PDF, Dokumen, dll.</div>
                    
                    <div class="dropzone-actions" style="margin-top: 12px;">
                        <button type="button" class="btn btn-primary" id="btnBrowseFiles" style="padding: 8px 18px; font-size: 13.5px;">
                            📁 Pilih Berkas (Choose Files)
                        </button>
                    </div>

                    <!-- Hidden file picker tanpa batasan tipe file -->
                    <input type="file" id="uploadFileInput" multiple style="display: none;">
                </div>

                <!-- Fallback selector jika browser membatasi synthetic click -->
                <div class="upload-fallback-bar">
                    <label for="uploadFileInputFallback" class="upload-fallback-label">
                        Atau pilih langsung melalui file selector sistem:
                    </label>
                    <input type="file" id="uploadFileInputFallback" multiple class="upload-fallback-input">
                </div>

                <!-- Queue & Progress Container -->
                <div id="uploadQueueContainer" style="display: none; margin-top: 14px;">
                    <div class="upload-queue-header">
                        <span class="upload-queue-title" id="uploadQueueTitle">Daftar Berkas Terpilih (0)</span>
                        <button type="button" id="btnClearUploadQueue" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 11px;">
                            ✕ Bersihkan Pilihan
                        </button>
                    </div>

                    <!-- List per file with real individual progress bars -->
                    <div class="upload-queue-list" id="uploadQueueList"></div>

                    <!-- Overall progress summary -->
                    <div class="upload-summary-bar" id="uploadSummaryBar" style="display: none;">
                        <span class="upload-summary-counter" id="uploadSummaryCounter">0 / 0 completed</span>
                        <span class="upload-summary-status" id="uploadSummaryStatus"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-modal-cancel" id="btnCancelUpload">Tutup</button>
                <button type="button" class="btn btn-primary" id="btnStartUpload" disabled>Mulai Upload</button>
            </div>
        </div>
    </div>

    <!-- Modal: New File (Sisipkan Berkas Baru) -->
    <div class="modal-backdrop" id="modalFile">
        <div class="modal-box">
            <form id="formNewFile">
                <div class="modal-header">
                    <h3>Buat / Sisipkan Berkas Baru</h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="font-size: 12.5px; color: #64748b; margin-bottom: 12px;">Lokasi: <code id="createFileTargetDisplay" style="color: #0066cc;">/</code></p>
                    <div class="form-group">
                        <label for="inputFileName">Nama Berkas</label>
                        <input type="text" id="inputFileName" class="form-control" required placeholder="contoh: index.php, style.css, script.js, config.json, note.txt" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="inputFileContent">Isi Berkas Awal (Opsional)</label>
                        <textarea id="inputFileContent" class="form-control" rows="6" placeholder="Ketik isi berkas di sini..." style="font-family: monospace; font-size: 12.5px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-modal-cancel">Batal</button>
                    <button type="submit" class="btn btn-primary">Buat Berkas</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: New Folder -->
    <div class="modal-backdrop" id="modalFolder">
        <div class="modal-box">
            <form id="formNewFolder">
                <div class="modal-header">
                    <h3>Buat Folder Baru</h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="font-size: 12.5px; color: #64748b; margin-bottom: 12px;">Lokasi: <code id="createFolderTargetDisplay" style="color: #0066cc; font-weight: 600;">/</code></p>
                    <div class="form-group">
                        <label for="inputFolderName">Nama Folder</label>
                        <input type="text" id="inputFolderName" class="form-control" required placeholder="contoh: assets, images, backup" autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-modal-cancel">Batal</button>
                    <button type="submit" class="btn btn-primary">Buat Folder</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Rename -->
    <div class="modal-backdrop" id="modalRename">
        <div class="modal-box">
            <form id="formRename">
                <input type="hidden" id="renameItemPath">
                <div class="modal-header">
                    <h3>Ganti Nama (Rename)</h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="renameNewName">Nama Baru</label>
                        <input type="text" id="renameNewName" class="form-control" required autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-modal-cancel">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Delete Confirmation -->
    <div class="modal-backdrop" id="modalDelete">
        <div class="modal-box">
            <form id="formDelete">
                <input type="hidden" id="deleteItemPath">
                <div class="modal-header">
                    <h3 id="deleteModalTitle">Konfirmasi Hapus</h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <p id="deleteItemPrompt">Apakah Anda yakin ingin menghapus <strong id="deleteItemName"></strong>?</p>
                    <div id="deleteMultiItemsContainer" style="display: none; max-height: 140px; overflow-y: auto; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; margin-top: 8px; font-size: 12px; font-family: monospace;"></div>
                    <div class="alert alert-danger" id="deleteWarningBox" style="margin-top: 12px; display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-modal-cancel">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus Permanen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Extract ZIP -->
    <div class="modal-backdrop" id="modalExtract">
        <div class="modal-box">
            <form id="formExtract">
                <input type="hidden" id="extractZipPath">
                <div class="modal-header">
                    <h3>Ekstrak Arsip ZIP</h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="margin-bottom: 12px;">Arsip: <strong id="extractZipName"></strong></p>
                    
                    <div class="form-group">
                        <label for="extractDestInput">Direktori Tujuan Ekstraksi:</label>
                        <input type="text" id="extractDestInput" class="form-control selected-dest-input" required autocomplete="off" style="font-family: monospace;">
                        <small style="color: #64748b; font-size: 11.5px; display: block; margin-top: 4px;">
                            Default: Current directory (<code><span id="extractCurrentPathHint">/</span></code>). Anda dapat mengubah direktori atau memilih dari folder tree di bawah.
                        </small>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label style="font-size: 12px; color: #475569;">Pilih dari Folder Tree:</label>
                        <div class="tree-container" id="extractTreeContainer"></div>
                    </div>

                    <div class="form-group" style="margin-top: 14px;">
                        <label>Penanganan Konflik File (Jika ada file yang sama):</label>
                        <div style="display: flex; flex-direction: column; gap: 6px; margin-top: 6px;">
                            <label style="font-weight: normal; font-size: 13px;">
                                <input type="radio" name="conflict_strategy" value="overwrite" checked> Overwrite (Timpa file yang sudah ada)
                            </label>
                            <label style="font-weight: normal; font-size: 13px;">
                                <input type="radio" name="conflict_strategy" value="skip"> Skip (Lewati file yang sudah ada)
                            </label>
                            <label style="font-weight: normal; font-size: 13px;">
                                <input type="radio" name="conflict_strategy" value="rename"> Rename (Simpan file baru dengan akhiran _copy)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-modal-cancel">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnStartExtract">Ekstrak</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Copy & Move -->
    <div class="modal-backdrop" id="modalCopyMove">
        <div class="modal-box">
            <form id="formCopyMove">
                <input type="hidden" id="copyMoveMode">
                <input type="hidden" id="copyMoveSourcePath">
                <div class="modal-header">
                    <h3 id="copyMoveModalTitle">Pilih Tujuan</h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="copyMoveMultiItemsContainer" style="display: none; max-height: 90px; overflow-y: auto; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 8px; margin-bottom: 12px; font-size: 12px; font-family: monospace;"></div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="copyMoveDestInput">Direktori Tujuan:</label>
                        <input type="text" id="copyMoveDestInput" class="form-control selected-dest-input" required autocomplete="off" style="font-family: monospace;">
                        <small style="color: #64748b; font-size: 11.5px; display: block; margin-top: 4px;">
                            Default: Current directory (<code><span id="copyMoveCurrentPathHint">/</span></code>). Anda dapat mengetik direktori tujuan atau memilih dari folder tree di bawah.
                        </small>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 12px; color: #475569;">Pilih dari Folder Tree:</label>
                        <div class="tree-container" id="copyMoveTreeContainer"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-modal-cancel">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitCopyMove">Proses</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: File Information -->
    <div class="modal-backdrop" id="modalInfo">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Informasi Berkas</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="infoModalContent"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-modal-cancel">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal: Plain Text Preview (Non-executable) -->
    <div class="modal-backdrop" id="modalPreview">
        <div class="modal-box modal-lg">
            <div class="modal-header">
                <h3 id="previewModalTitle">Pratinjau Berkas</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <pre class="preview-box"><code id="previewModalCode"></code></pre>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-modal-cancel">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal: File Editor (Edit File) -->
    <div class="modal-backdrop" id="modalEditor">
        <div class="modal-box modal-xl editor-modal-box">
            <form id="formEditor">
                <input type="hidden" id="editorFilePath">
                <div class="modal-header editor-header">
                    <div class="editor-header-left">
                        <span class="editor-icon">📝</span>
                        <h3 id="editorModalTitle" style="display: inline; margin-left: 6px;">Editor Berkas</h3>
                        <span class="editor-file-path" id="editorFilePathDisplay">/</span>
                        <span class="badge-dirty" id="editorDirtyBadge" style="display: none;">* Belum disimpan</span>
                    </div>
                    <button type="button" class="modal-close" id="btnEditorClose">&times;</button>
                </div>
                <div class="editor-toolbar">
                    <div class="editor-toolbar-left">
                        <span class="editor-stat" id="editorStatLines">Baris: 0</span>
                        <span class="editor-stat" id="editorStatChars">Karakter: 0</span>
                        <span class="editor-stat" id="editorStatEncoding">UTF-8</span>
                    </div>
                    <div class="editor-toolbar-right">
                        <select id="editorLangSelect" class="editor-lang-select" title="Pilih Bahasa Syntax Highlighting">
                            <option value="auto">Auto (Deteksi)</option>
                            <option value="php">PHP</option>
                            <option value="javascript">JavaScript</option>
                            <option value="json">JSON</option>
                            <option value="html">HTML</option>
                            <option value="css">CSS</option>
                            <option value="sql">SQL</option>
                            <option value="shell">Shell / Bash</option>
                            <option value="plain">Plain Text</option>
                        </select>
                        <button type="button" class="editor-btn-tool active" id="btnEditorHighlight" title="Toggle Syntax Highlighting">Syntax: ON</button>
                        <button type="button" class="editor-btn-tool" id="btnEditorWrap" title="Toggle Bungkus Baris Panjang (Word Wrap)">Wrap Teks</button>
                        <button type="button" class="editor-btn-tool" id="btnEditorFullscreen" title="Perbesar / Perkecil Tampilan Layar Penuh">Layar Penuh</button>
                        <span id="editorSaveStatus" class="editor-save-status"></span>
                    </div>
                </div>
                <div class="modal-body editor-body">
                    <div id="editorWarningBox" class="alert alert-warning" style="display: none; margin-bottom: 8px; font-size: 12px;"></div>
                    <div class="editor-container" id="editorContainer">
                        <pre class="code-editor-pre" id="editorPre" aria-hidden="true"><code id="editorCode"></code></pre>
                        <textarea id="editorContent" class="code-editor-textarea" spellcheck="false" autocomplete="off" wrap="off"></textarea>
                    </div>
                </div>
                <div class="modal-footer editor-footer">
                    <div class="editor-footer-hint">
                        <kbd>Ctrl</kbd> + <kbd>S</kbd> untuk menyimpan &bull; <kbd>Tab</kbd> untuk indentasi 4 spasi
                    </div>
                    <div class="editor-footer-actions">
                        <button type="button" class="btn btn-secondary" id="btnCancelEditor">Tutup</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveEditor">Simpan Perubahan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Compress to ZIP Archive -->
    <div class="modal-backdrop" id="modalCompress">
        <div class="modal-box">
            <form id="formCompress">
                <div class="modal-header">
                    <h3>Kompres ke ZIP (Compress Archive)</h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>Item yang akan dikompres:</label>
                        <div id="compressItemsList" class="compress-items-box"></div>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="compressZipNameInput">Nama File ZIP:</label>
                        <input type="text" id="compressZipNameInput" class="form-control" required placeholder="archive.zip" autocomplete="off" style="font-family: monospace;">
                        <small style="color: #64748b; font-size: 11.5px; display: block; margin-top: 4px;">
                            Ekstensi <code>.zip</code> akan otomatis ditambahkan jika tidak disertakan.
                        </small>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="compressDestInput">Direktori Tujuan:</label>
                        <input type="text" id="compressDestInput" class="form-control selected-dest-input" required autocomplete="off" style="font-family: monospace;">
                        <small style="color: #64748b; font-size: 11.5px; display: block; margin-top: 4px;">
                            Default: Current directory (<code><span id="compressCurrentPathHint">/</span></code>).
                        </small>
                    </div>
                    <div class="form-group">
                        <label style="font-size: 12px; color: #475569;">Pilih dari Folder Tree:</label>
                        <div class="tree-container" id="compressTreeContainer"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-modal-cancel">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnStartCompress">Kompres Sekarang</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Change Permissions (CHMOD) -->
    <div class="modal-backdrop" id="modalChmod">
        <div class="modal-box">
            <form id="formChmod">
                <input type="hidden" id="chmodItemPath">
                <div class="modal-header">
                    <h3>Ubah Hak Akses (Permissions)</h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="margin-bottom: 12px;">Berkas / Folder: <strong id="chmodItemName"></strong></p>
                    <div class="chmod-matrix-container">
                        <table class="chmod-table">
                            <thead>
                                <tr>
                                    <th>Kategori</th>
                                    <th>Read (r - 4)</th>
                                    <th>Write (w - 2)</th>
                                    <th>Execute (x - 1)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>User (Owner)</strong></td>
                                    <td class="text-center"><input type="checkbox" id="chmod_u_r" value="4" class="chmod-check"></td>
                                    <td class="text-center"><input type="checkbox" id="chmod_u_w" value="2" class="chmod-check"></td>
                                    <td class="text-center"><input type="checkbox" id="chmod_u_x" value="1" class="chmod-check"></td>
                                </tr>
                                <tr>
                                    <td><strong>Group</strong></td>
                                    <td class="text-center"><input type="checkbox" id="chmod_g_r" value="4" class="chmod-check"></td>
                                    <td class="text-center"><input type="checkbox" id="chmod_g_w" value="2" class="chmod-check"></td>
                                    <td class="text-center"><input type="checkbox" id="chmod_g_x" value="1" class="chmod-check"></td>
                                </tr>
                                <tr>
                                    <td><strong>World (Others)</strong></td>
                                    <td class="text-center"><input type="checkbox" id="chmod_w_r" value="4" class="chmod-check"></td>
                                    <td class="text-center"><input type="checkbox" id="chmod_w_w" value="2" class="chmod-check"></td>
                                    <td class="text-center"><input type="checkbox" id="chmod_w_x" value="1" class="chmod-check"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="chmod-numeric-group" style="margin-top: 14px; display: flex; align-items: center; gap: 12px;">
                        <label for="chmodOctalInput" style="font-weight: 600; font-size: 13px; margin: 0;">Nilai Oktal:</label>
                        <input type="text" id="chmodOctalInput" maxlength="4" class="form-control" style="width: 100px; font-family: monospace; font-size: 16px; font-weight: bold; text-align: center;" value="0644">
                        <span id="chmodRwxDisplay" style="font-family: monospace; font-size: 13px; color: #475569; background: #f1f5f9; padding: 6px 12px; border-radius: 4px; border: 1px solid #cbd5e1;">-rw-r--r--</span>
                    </div>
                    <small style="color: #64748b; font-size: 11.5px; display: block; margin-top: 8px;">
                        Standar hosting: <code>0644</code> untuk berkas biasa, <code>0755</code> untuk direktori atau skrip eksekusi.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-modal-cancel">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitChmod">Ubah Hak Akses</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Admin Settings (Ganti Username & Password) -->
    <div class="modal-backdrop" id="modalSettings">
        <div class="modal-box">
            <form id="formSettings">
                <div class="modal-header">
                    <h3>Pengaturan Akun Admin</h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="settingsAlert" class="alert" style="display: none; margin-bottom: 14px;"></div>
                    
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="settingsUsername">Username Login:</label>
                        <input type="text" id="settingsUsername" class="form-control" required autocomplete="username" placeholder="Masukkan username">
                        <small style="color: #64748b; font-size: 11px; display: block; margin-top: 4px;">
                            Username untuk masuk ke File Manager (minimal 3 karakter).
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="settingsCurrentPassword">Password Saat Ini (Konfirmasi Keamanan): <span style="color: #dc2626;">*</span></label>
                        <input type="password" id="settingsCurrentPassword" class="form-control" required autocomplete="current-password" placeholder="Masukkan password saat ini">
                        <small style="color: #64748b; font-size: 11px; display: block; margin-top: 4px;">
                            Wajib diisi untuk memverifikasi bahwa Anda adalah pemilik akun.
                        </small>
                    </div>

                    <div style="border-top: 1px solid #e2e8f0; margin: 16px 0 12px 0; padding-top: 12px;">
                        <span style="font-weight: 600; font-size: 12px; color: #334155; display: block; margin-bottom: 10px;">Ganti Password (Opsional):</span>
                        
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label for="settingsNewPassword">Password Baru:</label>
                            <input type="password" id="settingsNewPassword" class="form-control" autocomplete="new-password" placeholder="Kosongkan jika tidak ingin mengubah password">
                            <small style="color: #64748b; font-size: 11px; display: block; margin-top: 4px;">
                                Minimal 5 karakter jika ingin mengganti password.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="settingsConfirmPassword">Ulangi Password Baru:</label>
                            <input type="password" id="settingsConfirmPassword" class="form-control" autocomplete="new-password" placeholder="Ketik ulang password baru">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-modal-cancel">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitSettings">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Desktop Context Menu (Right Click) -->
    <div id="contextMenu" class="context-menu" style="display: none;">
        <div class="context-menu-item" data-action="open" id="ctxOpen" style="display: none;">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg></span>
            <span>Buka Folder</span>
        </div>
        <div class="context-menu-item" data-action="download" id="ctxDownload">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>
            <span>Unduh (Download)</span>
        </div>
        <div class="context-menu-item" data-action="preview" id="ctxPreview">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
            <span>Pratinjau (View)</span>
        </div>
        <div class="context-menu-item" data-action="edit" id="ctxEdit">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></span>
            <span>Edit Berkas</span>
        </div>
        <div class="context-menu-item" data-action="extract" id="ctxExtract">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/><line x1="12" y1="11" x2="12" y2="17"/><polyline points="9 14 12 11 15 14"/></svg></span>
            <span>Ekstrak ZIP</span>
        </div>
        <div class="context-menu-divider" id="ctxDiv1"></div>
        <div class="context-menu-item" data-action="compress" id="ctxCompress">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg></span>
            <span>Kompres ke ZIP</span>
        </div>
        <div class="context-menu-item" data-action="copy" id="ctxCopy">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg></span>
            <span>Salin ke (Copy)</span>
        </div>
        <div class="context-menu-item" data-action="duplicate" id="ctxDuplicate">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg></span>
            <span>Duplikat (Duplicate)</span>
        </div>
        <div class="context-menu-item" data-action="move" id="ctxMove">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg></span>
            <span>Pindahkan ke (Move)</span>
        </div>
        <div class="context-menu-item" data-action="rename" id="ctxRename">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
            <span>Ganti Nama</span>
        </div>
        <div class="context-menu-item" data-action="chmod" id="ctxChmod">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></span>
            <span>Hak Akses (Permissions)</span>
        </div>
        <div class="context-menu-item" data-action="info" id="ctxInfo">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
            <span>Informasi Berkas</span>
        </div>
        <div class="context-menu-divider" id="ctxDiv2"></div>
        <div class="context-menu-item ctx-danger" data-action="delete" id="ctxDelete">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></span>
            <span>Hapus (Delete)</span>
        </div>
        
        <!-- Context Menu items for empty area / directory blank space -->
        <div class="context-menu-item" data-action="new_file" id="ctxNewFile" style="display: none;">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg></span>
            <span>Buat Berkas Baru</span>
        </div>
        <div class="context-menu-item" data-action="new_folder" id="ctxNewFolder" style="display: none;">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg></span>
            <span>Buat Folder Baru</span>
        </div>
        <div class="context-menu-item" data-action="upload" id="ctxUpload" style="display: none;">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></span>
            <span>Unggah Berkas</span>
        </div>
        <div class="context-menu-item" data-action="refresh" id="ctxRefresh" style="display: none;">
            <span class="ctx-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg></span>
            <span>Segarkan (Refresh)</span>
        </div>
    </div>

    <script>
    function openSettingsModalFallback() {
        if (typeof window.hfmOpenSettingsModal === 'function') {
            window.hfmOpenSettingsModal();
            return;
        }
        var modal = document.getElementById('modalSettings');
        if (modal) {
            modal.classList.add('show');
            var userField = document.getElementById('settingsUsername');
            var userBadge = document.getElementById('headerUserBadge');
            if (userField && userBadge) {
                var uText = userBadge.textContent.replace(/^User:\s*/i, '').trim();
                if (uText) userField.value = uText;
            }
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('modalSettings');
        if (modal) {
            modal.querySelectorAll('.modal-close, .btn-modal-cancel').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    modal.classList.remove('show');
                });
            });
        }
    });
    </script>
    <script src="assets/js/app.js?v=<?= $jsVer ?>"></script>
</body>
</html>
