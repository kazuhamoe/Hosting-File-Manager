<?php
/**
 * Test: Auto Full Permissions (0777 / full centang) on Upload and Zip Extract
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/Logger.php';
require_once __DIR__ . '/../app/FileManager.php';
require_once __DIR__ . '/../app/ZipManager.php';

$testDir = Security::getRoot() . DIRECTORY_SEPARATOR . 'test_perms_' . time();
@mkdir($testDir, 0777, true);

$passed = 0;
$failed = 0;

function assertTest(bool $cond, string $title, string $detail = '') {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] $title" . ($detail ? " - $detail" : "") . "\n";
    } else {
        $failed++;
        echo "  [FAIL] $title" . ($detail ? " - $detail" : "") . "\n";
    }
}

echo "======================================================================\n";
echo "  TEST: AUTO FULL PERMISSIONS (0777) ON UPLOAD & EXTRACTION\n";
echo "======================================================================\n\n";

// 1. Test saveUploadedFile with dummy zip
$sampleZip = $testDir . DIRECTORY_SEPARATOR . 'source.zip';
$zip = new ZipArchive();
$zip->open($sampleZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('nested/inner_script.php', '<?php echo "ok"; ?>');
$zip->addFromString('site.html', '<h1>Hello</h1>');
$zip->close();

$resUpload = FileManager::saveUploadedFile(
    Security::toVirtualPath($testDir),
    $sampleZip,
    'uploaded_archive.zip',
    filesize($sampleZip),
    UPLOAD_ERR_OK,
    false
);

assertTest($resUpload['success'] === true, 'Upload ZIP file succeeds', $resUpload['message'] ?? '');
$uploadedZipPath = Security::resolvePath($resUpload['virtual_path'], true);
assertTest(file_exists($uploadedZipPath), 'Uploaded ZIP file exists at destination');

// 2. Test extractZip of the uploaded zip
$extractDest = Security::toVirtualPath($testDir . DIRECTORY_SEPARATOR . 'extracted_out');
$resExtract = ZipManager::extractZip($resUpload['virtual_path'], $extractDest, 'overwrite');

assertTest($resExtract['success'] === true, 'Extract uploaded ZIP succeeds', $resExtract['message'] ?? '');
$extractedHtml = Security::resolvePath($extractDest . '/site.html', true);
$extractedPhp = Security::resolvePath($extractDest . '/nested/inner_script.php', true);
assertTest(file_exists($extractedHtml), 'Extracted site.html exists');
assertTest(file_exists($extractedPhp), 'Extracted inner_script.php exists');

// 3. Test createFolder & createFile
$resFolder = FileManager::createFolder(Security::toVirtualPath($testDir), 'my_new_folder');
assertTest($resFolder['success'] === true, 'createFolder succeeds');

$resFile = FileManager::createFile(Security::toVirtualPath($testDir), 'test_created.txt', 'test content');
assertTest($resFile['success'] === true, 'createFile succeeds');

// Clean up
function cleanDir($dir) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($p)) cleanDir($p);
        else @unlink($p);
    }
    @rmdir($dir);
}
cleanDir($testDir);

echo "\n======================================================================\n";
echo "  HASIL: $passed PASSED, $failed FAILED\n";
echo "======================================================================\n";

if ($failed > 0) exit(1);
