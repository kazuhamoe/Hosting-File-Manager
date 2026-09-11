<?php
/**
 * Test Suite Komprehensif: Validasi dan Keamanan Ekstraksi ZIP
 * Menguji seluruh skenario ZIP normal (index.php, folders, CSS/JS, images, nested)
 * dan seluruh skenario keamanan (Zip Slip, ../, absolute path, encoded traversal, protected overwrite).
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/ZipManager.php';
require_once __DIR__ . '/../app/FileManager.php';

$testPassed = 0;
$testFailed = 0;

function runTest(string $name, bool $condition, string $detail = ''): void {
    global $testPassed, $testFailed;
    if ($condition) {
        $testPassed++;
        echo "  [PASS] " . str_pad($name, 45) . " " . $detail . PHP_EOL;
    } else {
        $testFailed++;
        echo "  [FAIL] " . str_pad($name, 45) . " " . $detail . PHP_EOL;
    }
}

echo "======================================================================" . PHP_EOL;
echo "  TEST SUITE: ZIP EXTRACTION & SECURITY AUDIT" . PHP_EOL;
echo "======================================================================" . PHP_EOL;

$testRoot = Security::getRoot();
$testWorkDir = $testRoot . DIRECTORY_SEPARATOR . 'test_zip_suite';
if (is_dir($testWorkDir)) {
    // Bersihkan folder sisa sebelumnya
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testWorkDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $fileinfo) {
        $fileinfo->isDir() ? rmdir($fileinfo->getRealPath()) : unlink($fileinfo->getRealPath());
    }
    rmdir($testWorkDir);
}
mkdir($testWorkDir, 0777, true);
$testVirtualDir = '/test_zip_suite';

// ----------------------------------------------------------------------
// 1. PENGUJIAN ZIP NORMAL (HARUS BERHASIL)
// ----------------------------------------------------------------------
echo "\n--- 1. PENGUJIAN ZIP NORMAL (NORMAL ZIP EXTRACTIONS) ---" . PHP_EOL;

// 1a. ZIP berisi index.php
$zipIndexFile = $testWorkDir . '/test_index.zip';
$za = new ZipArchive();
$za->open($zipIndexFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$za->addFromString('index.php', '<?php echo "Hello from website index.php"; ?>');
$za->close();

$res1 = ZipManager::extractZip($testVirtualDir . '/test_index.zip', $testVirtualDir . '/out_index');
runTest(
    "ZIP berisi index.php",
    $res1['success'] && file_exists($testWorkDir . '/out_index/index.php'),
    "index.php website berhasil diekstrak tanpa false positive"
);

// 1b. ZIP berisi folder
$zipFolderFile = $testWorkDir . '/test_folders.zip';
$za = new ZipArchive();
$za->open($zipFolderFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$za->addEmptyDir('admin');
$za->addEmptyDir('includes');
$za->addFromString('admin/dashboard.php', '<?php echo "Admin"; ?>');
$za->addFromString('includes/functions.php', '<?php function test() {} ?>');
$za->close();

$res2 = ZipManager::extractZip($testVirtualDir . '/test_folders.zip', $testVirtualDir . '/out_folders');
runTest(
    "ZIP berisi folder & subfolder",
    $res2['success'] && is_dir($testWorkDir . '/out_folders/admin') && file_exists($testWorkDir . '/out_folders/admin/dashboard.php'),
    "Struktur folder & file berhasil terbentuk"
);

// 1c. ZIP berisi CSS dan JS
$zipAssetsFile = $testWorkDir . '/test_assets.zip';
$za = new ZipArchive();
$za->open($zipAssetsFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$za->addFromString('assets/css/style.css', 'body { color: red; }');
$za->addFromString('assets/js/main.js', 'console.log("active");');
$za->close();

$res3 = ZipManager::extractZip($testVirtualDir . '/test_assets.zip', $testVirtualDir . '/out_assets');
runTest(
    "ZIP berisi CSS dan JS",
    $res3['success'] && file_exists($testWorkDir . '/out_assets/assets/css/style.css') && file_exists($testWorkDir . '/out_assets/assets/js/main.js'),
    "Berkas CSS dan JS terekstrak dengan benar"
);

// 1d. ZIP berisi images
$zipImagesFile = $testWorkDir . '/test_images.zip';
$za = new ZipArchive();
$za->open($zipImagesFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$za->addFromString('images/logo.png', 'FAKEPNGDATA');
$za->addFromString('images/banner.jpg', 'FAKEJPGDATA');
$za->addFromString('images/icon.webp', 'FAKEWEBPDATA');
$za->close();

$res4 = ZipManager::extractZip($testVirtualDir . '/test_images.zip', $testVirtualDir . '/out_images');
runTest(
    "ZIP berisi images (PNG, JPG, WEBP)",
    $res4['success'] && file_exists($testWorkDir . '/out_images/images/logo.png') && file_exists($testWorkDir . '/out_images/images/icon.webp'),
    "Berkas gambar terekstrak lengkap"
);

// 1e. ZIP nested folder mendalam
$zipNestedFile = $testWorkDir . '/test_nested.zip';
$za = new ZipArchive();
$za->open($zipNestedFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$za->addFromString('website/assets/theme/dark/css/theme.css', '/* theme */');
$za->close();

$res5 = ZipManager::extractZip($testVirtualDir . '/test_nested.zip', $testVirtualDir . '/out_nested');
runTest(
    "ZIP nested folder mendalam",
    $res5['success'] && file_exists($testWorkDir . '/out_nested/website/assets/theme/dark/css/theme.css'),
    "Hierarki nested folder dalam terekstrak utuh"
);

// 1f. ZIP dengan .htaccess di dalam subdirektori (admin/.htaccess)
$zipHtaccessFile = $testWorkDir . '/test_htaccess.zip';
$za = new ZipArchive();
$za->open($zipHtaccessFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$za->addFromString('admin/.htaccess', 'Deny from all');
$za->addFromString('admin/login.php', '<?php login(); ?>');
$za->close();

$res6 = ZipManager::extractZip($testVirtualDir . '/test_htaccess.zip', $testVirtualDir . '/out_htaccess');
runTest(
    "ZIP dengan .htaccess di subdirektori",
    $res6['success'] && file_exists($testWorkDir . '/out_htaccess/admin/.htaccess'),
    "Subfolder .htaccess diizinkan dan tidak terblokir false-positive"
);

// 1g. ZIP dengan folder 'app' milik CMS/website user
$zipAppFile = $testWorkDir . '/test_user_app.zip';
$za = new ZipArchive();
$za->open($zipAppFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$za->addFromString('myproject/app/controllers/Home.php', '<?php class Home {} ?>');
$za->close();

$res7 = ZipManager::extractZip($testVirtualDir . '/test_user_app.zip', $testVirtualDir . '/out_app');
runTest(
    "ZIP dengan folder app/ milik user CMS",
    $res7['success'] && file_exists($testWorkDir . '/out_app/myproject/app/controllers/Home.php'),
    "Folder app/ proyek user diizinkan dan tidak terblokir"
);

// ----------------------------------------------------------------------
// 2. PENGUJIAN KEAMANAN (SECURITY TESTS - HARUS DITOLAK / REJECT)
// ----------------------------------------------------------------------
echo "\n--- 2. PENGUJIAN KEAMANAN (SECURITY AUDIT - MUST REJECT) ---" . PHP_EOL;

// 2a. Traversal: ../
$zipTrav1 = $testWorkDir . '/test_trav1.zip';
$za = new ZipArchive();
$za->open($zipTrav1, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$za->addFromString('../escaped.txt', 'malicious content');
$za->close();

$resSec1 = ZipManager::extractZip($testVirtualDir . '/test_trav1.zip', $testVirtualDir . '/out_sec1');
runTest(
    "Security: Traversal (../)",
    !$resSec1['success'],
    "Ditolak aman: " . $resSec1['message']
);

// 2b. Traversal: ../../
$zipTrav2 = $testWorkDir . '/test_trav2.zip';
$za = new ZipArchive();
$za->open($zipTrav2, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$za->addFromString('../../config.php', 'malicious config');
$za->close();

$resSec2 = ZipManager::extractZip($testVirtualDir . '/test_trav2.zip', $testVirtualDir . '/out_sec2');
runTest(
    "Security: Traversal (../../)",
    !$resSec2['success'],
    "Ditolak aman: " . $resSec2['message']
);

// 2c. Absolute path: /etc/passwd atau C:/
$zipAbs = $testWorkDir . '/test_abs.zip';
$za = new ZipArchive();
$za->open($zipAbs, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$za->addFromString('/etc/passwd', 'malicious');
$za->close();

$resSec3 = ZipManager::extractZip($testVirtualDir . '/test_abs.zip', $testVirtualDir . '/out_sec3');
runTest(
    "Security: Absolute Path (/etc/passwd)",
    !$resSec3['success'],
    "Ditolak aman: " . $resSec3['message']
);

// 2d. Windows Drive Path: C:/boot.ini
$zipDrive = $testWorkDir . '/test_drive.zip';
$za = new ZipArchive();
$za->open($zipDrive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$za->addFromString('C:/boot.ini', 'malicious');
$za->close();

$resSec4 = ZipManager::extractZip($testVirtualDir . '/test_drive.zip', $testVirtualDir . '/out_sec4');
runTest(
    "Security: Windows Drive Path (C:/...)",
    !$resSec4['success'],
    "Ditolak aman: " . $resSec4['message']
);

// 2e. Encoded traversal: %2e%2e%2f
$zipEncoded = $testWorkDir . '/test_encoded.zip';
$za = new ZipArchive();
$za->open($zipEncoded, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$za->addFromString('%2e%2e%2fconfig.php', 'malicious');
$za->close();

$resSec5 = ZipManager::extractZip($testVirtualDir . '/test_encoded.zip', $testVirtualDir . '/out_sec5');
runTest(
    "Security: Encoded Traversal (%2e%2e%2f)",
    !$resSec5['success'],
    "Ditolak aman: " . $resSec5['message']
);

// 2f. Preserve existing credentials.json from being overwritten during extract
$zipCred = $testWorkDir . '/test_cred_hack.zip';
$za = new ZipArchive();
$za->open($zipCred, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$za->addFromString('sub_cred/credentials.json', json_encode(['username' => 'hacker', 'password' => 'hacked']));
$za->close();

$destCredDir = $testWorkDir . '/out_cred';
@mkdir($destCredDir . '/sub_cred', 0755, true);
file_put_contents($destCredDir . '/sub_cred/credentials.json', json_encode(['username' => 'admin_original', 'password' => 'safe']));

$resCredExtract = ZipManager::extractZip($testVirtualDir . '/test_cred_hack.zip', $testVirtualDir . '/out_cred', 'overwrite');
$credContent = json_decode(file_get_contents($destCredDir . '/sub_cred/credentials.json'), true);

runTest(
    "Security: Protected Credentials Preservation",
    $resCredExtract['success'] && isset($credContent['username']) && $credContent['username'] === 'admin_original',
    "credentials.json asli tetap dipertahankan dan tidak tertimpa oleh arsip ZIP"
);

// 2g. Normal website files (index.php, config.php, app/) must NOT be blocked (Anti False-Positive)
$appDir = realpath(dirname(__DIR__));
$normalCheck1 = Security::checkExtractProtection($appDir . '/index.php', 'index.php');
$normalCheck2 = Security::checkExtractProtection($appDir . '/config.php', 'config.php');
$normalCheck3 = Security::checkExtractProtection($appDir . '/app/models/Test.php', 'app/models/Test.php');
runTest(
    "Anti False-Positive: Normal files allowed",
    !$normalCheck1['is_protected'] && !$normalCheck2['is_protected'] && !$normalCheck3['is_protected'],
    "index.php, config.php, dan app/ user 100% diizinkan diekstrak"
);

// 2h. Full Self-Package Extraction to Root
$resPkgCheck = ZipManager::extractZip('/hosting-file-manager.zip', '/', 'overwrite');
runTest(
    "Anti False-Positive: Full package extract to root",
    $resPkgCheck['success'] && $resPkgCheck['extracted_count'] > 0,
    "Paket rilis hosting-file-manager.zip berhasil diekstrak ke root: " . $resPkgCheck['message']
);

// ----------------------------------------------------------------------
// 3. PENGUJIAN BERKAS REAL-WORLD (Cortina-Hosting.zip jika tersedia)
// ----------------------------------------------------------------------
echo "\n--- 3. PENGUJIAN BERKAS REAL-WORLD (Cortina-Hosting.zip) ---" . PHP_EOL;

$cortinaPath = 'C:/xampp/htdocs/Cortina-Hosting.zip';
if (file_exists($cortinaPath)) {
    $tempCortina = $testWorkDir . '/Cortina-Hosting.zip';
    @copy($cortinaPath, $tempCortina);
    $resCortina = ZipManager::extractZip($testVirtualDir . '/Cortina-Hosting.zip', $testVirtualDir . '/out_cortina', 'overwrite');
    runTest(
        "Extract Cortina-Hosting.zip",
        $resCortina['success'] && file_exists($testWorkDir . '/out_cortina/admin/.htaccess'),
        "Arsip nyata berhasil diekstrak: " . $resCortina['message']
    );
} else {
    echo "  [INFO] Berkas Cortina-Hosting.zip tidak ditemukan, lewati uji arsip nyata." . PHP_EOL;
}

// Bersihkan testWorkDir
if (is_dir($testWorkDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testWorkDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $fileinfo) {
        $fileinfo->isDir() ? @rmdir($fileinfo->getRealPath()) : @unlink($fileinfo->getRealPath());
    }
    @rmdir($testWorkDir);
}

echo "\n======================================================================" . PHP_EOL;
echo "  HASIL: $testPassed PASSED, $testFailed FAILED" . PHP_EOL;
echo "======================================================================" . PHP_EOL;

if ($testFailed > 0) {
    exit(1);
}
