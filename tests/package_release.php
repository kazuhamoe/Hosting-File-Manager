<?php
/**
 * Script untuk membuat paket arsip ZIP siap produksi.
 */

$baseDir = dirname(__DIR__);
$zipFileName = 'hosting-file-manager.zip';
$zipFilePath = $baseDir . DIRECTORY_SEPARATOR . $zipFileName;

if (file_exists($zipFilePath)) {
    @unlink($zipFilePath);
}

$zip = new ZipArchive();
if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo "ERROR: Gagal membuat arsip ZIP: $zipFilePath\n";
    exit(1);
}

echo "Membuat paket ZIP produksi...\n";

// 1. Root files
$zip->addFile($baseDir . '/index.php', 'index.php');
$zip->addFile($baseDir . '/config.php', 'config.php');
$zip->addFile($baseDir . '/updater.php', 'updater.php');
$zip->addFile($baseDir . '/README.md', 'README.md');
if (file_exists($baseDir . '/README.id.md')) $zip->addFile($baseDir . '/README.id.md', 'README.id.md');
if (file_exists($baseDir . '/SECURITY.md')) $zip->addFile($baseDir . '/SECURITY.md', 'SECURITY.md');
if (file_exists($baseDir . '/favicon.ico')) $zip->addFile($baseDir . '/favicon.ico', 'favicon.ico');
echo "  + index.php\n  + config.php\n  + updater.php\n  + README.md\n  + README.id.md\n  + SECURITY.md\n  + favicon.ico\n";

// 2. Core App Classes & app/index.php
$zip->addEmptyDir('app');
$appFiles = glob($baseDir . '/app/*.php');
foreach ($appFiles as $file) {
    $zip->addFile($file, 'app/' . basename($file));
    echo "  + app/" . basename($file) . "\n";
}

// 3. Frontend Assets
$zip->addEmptyDir('assets');
$zip->addEmptyDir('assets/css');
$zip->addEmptyDir('assets/js');
$zip->addEmptyDir('assets/icons');
$zip->addFile($baseDir . '/assets/css/style.css', 'assets/css/style.css');
$zip->addFile($baseDir . '/assets/js/app.js', 'assets/js/app.js');

$iconFiles = glob($baseDir . '/assets/icons/*.*');
foreach ($iconFiles as $ic) {
    $zip->addFile($ic, 'assets/icons/' . basename($ic));
}
echo "  + assets/css/style.css\n  + assets/js/app.js\n  + assets/icons/ (all formats)\n";

// 4. Storage (Clean production state)
$zip->addEmptyDir('storage');
$zip->addEmptyDir('storage/logs');
$zip->addEmptyDir('storage/temp');
if (file_exists($baseDir . '/storage/.htaccess')) {
    $zip->addFile($baseDir . '/storage/.htaccess', 'storage/.htaccess');
    echo "  + storage/.htaccess\n";
}
if (file_exists($baseDir . '/storage/index.php')) {
    $zip->addFile($baseDir . '/storage/index.php', 'storage/index.php');
    echo "  + storage/index.php\n";
}
$zip->addFromString('storage/temp/.gitkeep', '');
echo "  + storage/temp/.gitkeep\n";

$zip->close();

// Also copy to hosting-file-manager.zip
$copyPath = $baseDir . DIRECTORY_SEPARATOR . 'hosting-file-manager.zip';
@copy($zipFilePath, $copyPath);

$sizeKb = round(filesize($zipFilePath) / 1024, 2);
echo "\n======================================================\n";
echo "BERHASIL MEMBUAT PAKET PRODUKSI:\n";
echo "File 1: $zipFilePath ($sizeKb KB)\n";
echo "File 2: $copyPath ($sizeKb KB)\n";
echo "======================================================\n";
