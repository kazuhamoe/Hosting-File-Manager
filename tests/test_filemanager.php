<?php
/**
 * Automated Test Suite for Hosting File Manager
 * Menjalankan pengujian keamanan, sandboxing, ZIP slip, dan operasi berkas.
 */

define('APP_INIT', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/Logger.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/FileManager.php';
require_once __DIR__ . '/../app/ZipManager.php';

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] $description\n";
        $passed++;
    } else {
        echo " [FAIL] $description\n";
        $failed++;
    }
}

echo "\n======================================================\n";
echo "  MEMULAI PENGUJIAN OTOMATIS HOSTING FILE MANAGER\n";
echo "======================================================\n\n";

// --------------------------------------------------------------------------
// TEST GROUP 1: Path Traversal & Sandbox Boundary
// --------------------------------------------------------------------------
echo "--- 1. Security & Path Traversal Protection ---\n";

$res1 = Security::resolvePath('../../config.php', false);
assertTest("Path traversal '../../config.php' harus ditolak (null)", $res1 === null);

$res2 = Security::resolvePath('..\\..\\index.php', false);
assertTest("Windows traversal '..\\..\\index.php' harus ditolak (null)", $res2 === null);

$res3 = Security::resolvePath('../../../etc/passwd', false);
assertTest("Linux traversal '../../../etc/passwd' harus ditolak (null)", $res3 === null);

$res4 = Security::resolvePath('C:/Windows/System32', false);
assertTest("Drive letter absolute path 'C:/Windows/System32' harus ditolak (null)", $res4 === null);

$res5 = Security::resolvePath('/documents/readme.txt', true);
assertTest("Path valid di sandbox '/documents/readme.txt' berhasil di-resolve", $res5 !== null && file_exists($res5));

$virtual = Security::toVirtualPath($res5);
assertTest("Virtual path tidak boleh membocorkan absolute drive server", $virtual === '/documents/readme.txt');

// --------------------------------------------------------------------------
// TEST GROUP 2: Protected Files Protection
// --------------------------------------------------------------------------
echo "\n--- 2. Protected Files Protection ---\n";

assertTest("index.php harus terproteksi", Security::isProtected('index.php'));
assertTest("config.php harus terproteksi", Security::isProtected('config.php'));
assertTest("app/Security.php harus terproteksi", Security::isProtected('app/Security.php'));
assertTest("documents/readme.txt TIDAK terproteksi", !Security::isProtected('documents/readme.txt'));

// --------------------------------------------------------------------------
// TEST GROUP 3: ZIP Slip & Archive Validation
// --------------------------------------------------------------------------
echo "\n--- 3. ZIP Slip & Security Validation ---\n";

$tempDir = defined('TEMP_PATH') ? TEMP_PATH : (__DIR__ . '/../storage/temp');
if (!is_dir($tempDir)) @mkdir($tempDir, 0755, true);

// Buat ZIP jahat dengan entry traversal: ../escaped.txt
$badZipPath = $tempDir . '/malicious_test.zip';
$zip = new ZipArchive();
if ($zip->open($badZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $zip->addFromString('../escaped.txt', 'This should not be written outside root!');
    $zip->close();
}

$destReal = Security::getRoot();
$badZip = new ZipArchive();
$badZip->open($badZipPath);
$valResult = ZipManager::validateArchiveEntries($badZip, $destReal);
$badZip->close();

assertTest("ZIP berisi traversal '../escaped.txt' harus ditolak validasinya", $valResult['valid'] === false);
@unlink($badZipPath);

// Buat ZIP valid di dalam ALLOWED_ROOT
$goodZipPath = Security::getRoot() . DIRECTORY_SEPARATOR . 'valid_test.zip';
$zipGood = new ZipArchive();
if ($zipGood->open($goodZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $zipGood->addFromString('hello.txt', 'Hello World from ZIP');
    $zipGood->addFromString('folder_in_zip/test.txt', 'Nested test file');
    $zipGood->close();
}

// Ekstrak ZIP valid ke /test_extract
$extractRes = ZipManager::extractZip('/valid_test.zip', '/test_extract', 'overwrite');
assertTest("Ekstraksi ZIP valid berhasil", $extractRes['success'] === true);

$extractedHello = Security::resolvePath('/test_extract/hello.txt', true);
assertTest("File hasil ekstrak 'hello.txt' ada di direktori tujuan", $extractedHello !== null && file_exists($extractedHello));

// Test Conflict Strategy: Rename
$extractRenameRes = ZipManager::extractZip('/valid_test.zip', '/test_extract', 'rename');
assertTest("Ekstraksi ulang dengan conflict=rename berhasil", $extractRenameRes['success'] === true && $extractRenameRes['renamed_count'] > 0);

$extractedCopy = Security::resolvePath('/test_extract/hello_copy.txt', true);
assertTest("File konflik diberi nama unik 'hello_copy.txt'", $extractedCopy !== null && file_exists($extractedCopy));

@unlink($goodZipPath);

// --------------------------------------------------------------------------
// TEST GROUP 4: Filesystem Operations (CRUD)
// --------------------------------------------------------------------------
echo "\n--- 4. Filesystem Operations (CRUD) ---\n";

// Create Folder
$mkRes = FileManager::createFolder('/', 'unit_test_folder');
assertTest("Membuat folder 'unit_test_folder' berhasil", $mkRes['success'] === true);

// Rename Folder
$renRes = FileManager::renameItem('/unit_test_folder', 'unit_test_renamed');
assertTest("Rename folder ke 'unit_test_renamed' berhasil", $renRes['success'] === true);

// Copy Item
$cpRes = FileManager::copyItem('/unit_test_renamed', '/test_extract');
assertTest("Salin folder ke '/test_extract' berhasil", $cpRes['success'] === true);

// List Directory
$listRes = FileManager::listDirectory('/test_extract');
assertTest("Listing direktori '/test_extract' mengembalikan item", $listRes['success'] === true && $listRes['count'] > 0);

// Delete Folder
$delRes1 = FileManager::deleteItem('/unit_test_renamed');
$delRes2 = FileManager::deleteItem('/test_extract');
assertTest("Menghapus folder pengujian berhasil", $delRes1['success'] === true && $delRes2['success'] === true);

// Proteksi agar index.php tidak bisa dihapus
$delProtected = FileManager::deleteItem('/../index.php');
assertTest("Percobaan menghapus file sistem di luar root gagal/ditolak", $delProtected['success'] === false);

// --------------------------------------------------------------------------
// TEST GROUP 5: Text Preview & File Info
// --------------------------------------------------------------------------
echo "\n--- 5. Text Preview & Info ---\n";

$previewRes = FileManager::getFilePreview('/documents/readme.txt');
assertTest("Pratinjau plain-text readme.txt berhasil", $previewRes['success'] === true && !empty($previewRes['content']));

$infoRes = FileManager::getItemInfo('/documents/readme.txt');
assertTest("Pengambilan metadata file info berhasil", $infoRes['success'] === true && $infoRes['name'] === 'readme.txt');

// --------------------------------------------------------------------------
// TEST GROUP 6: Authentication & Rate Limiting
// --------------------------------------------------------------------------
echo "\n--- 6. Authentication & Rate Limiting ---\n";

$_SERVER['REMOTE_ADDR'] = '192.168.1.99'; // Test IP terisolasi
$loginSuccess = Auth::login('admin', 'Admin#12345');
assertTest("Login dengan kredensial benar berhasil", $loginSuccess['success'] === true);

Auth::logout();
assertTest("Logout berhasil mengosongkan status login", !Auth::check());

// Test Rate Limiting
$_SERVER['REMOTE_ADDR'] = '192.168.1.100'; // Target IP lockout
for ($i = 1; $i <= 5; $i++) {
    Auth::login('admin', 'WrongPassword');
}
$lockedOutRes = Auth::login('admin', 'WrongPassword');
assertTest("Percobaan login ke-6 diblokir oleh rate limiter", strpos($lockedOutRes['message'], 'Terlalu banyak percobaan') !== false);

// --------------------------------------------------------------------------
// RINGKASAN
// --------------------------------------------------------------------------
echo "\n======================================================\n";
echo "  HASIL AKHIR: $passed PASSED, $failed FAILED\n";
echo "======================================================\n\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
