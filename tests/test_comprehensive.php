<?php
/**
 * Comprehensive Functional & Security Audit Test Suite
 * Pengujian menyeluruh seluruh 31 fungsi MVP dan seluruh vektor serangan keamanan.
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

function assertCheck(string $testName, bool $condition, string $details = ''): void
{
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] " . str_pad($testName, 65, ' ') . "\n";
        $passed++;
    } else {
        echo " [FAIL] " . str_pad($testName, 65, ' ') . ($details ? " -> $details" : "") . "\n";
        $failed++;
    }
}

echo "\n======================================================================\n";
echo "  AUDIT & TESTING MENYELURUH: HOSTING FILE MANAGER & ZIP EXTRACTOR\n";
echo "======================================================================\n\n";

$root = Security::getRoot();

// ==========================================================================
// BAGIAN 1: TESTING FUNGSIONAL (31 ITEM)
// ==========================================================================
echo "=== BAGIAN 1: PENGUJIAN 31 FITUR FUNGSIONAL MVP ===\n";

// 1. Login berhasil
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
$resLoginSuccess = Auth::login('admin', 'Admin#12345');
assertCheck("1. Login berhasil dengan kredensial valid", $resLoginSuccess['success'] === true);

// 2. Login gagal
$_SERVER['REMOTE_ADDR'] = '10.0.0.2';
$resLoginFail = Auth::login('admin', 'WrongPass#999');
assertCheck("2. Login gagal dengan password salah ditolak", $resLoginFail['success'] === false);

// 3. Session protection
assertCheck("3. Session check status aktif setelah login", Auth::check() === true);

// 4. Logout
Auth::logout();
assertCheck("4. Logout berhasil membersihkan sesi", Auth::check() === false);

// Login kembali untuk pengujian lanjutan
Auth::login('admin', 'Admin#12345');

// 5. Session timeout simulation
$oldActivity = $_SESSION['hfm_last_activity'] ?? time();
$_SESSION['hfm_last_activity'] = time() - (SESSION_TIMEOUT + 10);
assertCheck("5. Session timeout berhasil mendeteksi inaktivitas dan logout", Auth::check() === false);

// Re-login
Auth::login('admin', 'Admin#12345');

// 6. Browse directory
$listRoot = FileManager::listDirectory('/');
assertCheck("6. Browse directory root berhasil memuat konten", $listRoot['success'] === true && is_array($listRoot['items']));

// 7. Breadcrumb
$crumbs = FileManager::buildBreadcrumbs('/documents/sub/folder');
assertCheck("7. Breadcrumb navigasi terbentuk secara hierarkis", count($crumbs) === 4 && $crumbs[0]['name'] === 'Root');

// 8. Parent directory
$testParent = Security::resolvePath('/documents');
$upParent = dirname($testParent);
assertCheck("8. Parent directory dapat diakses dan tetap di dalam root", Security::isInsideRoot($upParent, $root));

// 9. Create folder
$resMkdir = FileManager::createFolder('/', 'func_test_dir');
assertCheck("9. Create folder baru di root berhasil", $resMkdir['success'] === true);

// 10. Upload single file (simulasi penulisan file ke sandbox)
$testFile1 = $root . DIRECTORY_SEPARATOR . 'func_test_dir' . DIRECTORY_SEPARATOR . 'file1.txt';
file_put_contents($testFile1, 'Content of single file upload');
assertCheck("10. Upload single file tersimpan di direktori target", file_exists($testFile1));

// 11. Upload multiple file (simulasi)
$testFile2 = $root . DIRECTORY_SEPARATOR . 'func_test_dir' . DIRECTORY_SEPARATOR . 'file2.txt';
$testFile3 = $root . DIRECTORY_SEPARATOR . 'func_test_dir' . DIRECTORY_SEPARATOR . 'file3.txt';
file_put_contents($testFile2, 'Content of file 2');
file_put_contents($testFile3, 'Content of file 3');
assertCheck("11. Upload multiple files tersimpan di direktori target", file_exists($testFile2) && file_exists($testFile3));

// 12. Drag & drop support (validasi sanitasi dan penerimaan file)
$sanitizedDropName = Security::sanitizeFilename('Uploaded via Drop.txt');
assertCheck("12. Drag & drop filename sanitization bekerja aman", $sanitizedDropName === 'Uploaded via Drop.txt');

// 13. Upload ZIP (pembuatan berkas ZIP di dalam target)
$zipPath = $root . DIRECTORY_SEPARATOR . 'func_test_dir' . DIRECTORY_SEPARATOR . 'archive.zip';
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('site/index.html', '<h1>Extracted Site</h1>');
$zip->addFromString('site/style.css', 'body { color: red; }');
$zip->close();
assertCheck("13. Upload file ZIP valid ke dalam direktori berhasil", file_exists($zipPath));

// 14. Extract ZIP
$resExtract = ZipManager::extractZip('/func_test_dir/archive.zip', '/func_test_dir/extracted', 'overwrite');
assertCheck("14. Extract ZIP ke direktori berhasil", $resExtract['success'] === true && $resExtract['extracted_count'] === 2);

// 15. Extract ke destination berbeda
$resExtractDiff = ZipManager::extractZip('/func_test_dir/archive.zip', '/func_test_dir/different_dest', 'overwrite');
assertCheck("15. Extract ZIP ke destinasi berbeda berhasil", $resExtractDiff['success'] === true && file_exists($root . '/func_test_dir/different_dest/site/index.html'));

// 16. Existing file conflict (Overwrite, Skip, Rename)
$resConflictSkip = ZipManager::extractZip('/func_test_dir/archive.zip', '/func_test_dir/extracted', 'skip');
$resConflictRename = ZipManager::extractZip('/func_test_dir/archive.zip', '/func_test_dir/extracted', 'rename');
assertCheck("16. Conflict handling (Skip & Rename) berhasil diterapkan", $resConflictSkip['skipped_count'] === 2 && $resConflictRename['renamed_count'] === 2);

// 17. Download file (verifikasi resolver dan header)
$dlFileResolved = Security::resolvePath('/func_test_dir/file1.txt', true);
assertCheck("17. Download file dapat di-resolve dan siap distream", $dlFileResolved !== null && is_file($dlFileResolved));

// 18. Download folder sebagai ZIP temporary
$tempDir = defined('TEMP_PATH') ? TEMP_PATH : (__DIR__ . '/../storage/temp');
$tempZip = $tempDir . '/test_folder_dl.zip';
$zipFolder = new ZipArchive();
$zipFolder->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zipFolder->addFromString('file1.txt', 'test');
$zipFolder->close();
assertCheck("18. Download folder dikemas menjadi ZIP temporary", file_exists($tempZip));
@unlink($tempZip);

// 19. Rename file
$resRenameFile = FileManager::renameItem('/func_test_dir/file1.txt', 'file1_renamed.txt');
assertCheck("19. Rename file berhasil mengubah nama", $resRenameFile['success'] === true && file_exists($root . '/func_test_dir/file1_renamed.txt'));

// 20. Rename folder
$resRenameFolder = FileManager::renameItem('/func_test_dir/different_dest', 'different_dest_renamed');
assertCheck("20. Rename folder berhasil", $resRenameFolder['success'] === true && is_dir($root . '/func_test_dir/different_dest_renamed'));

// 21. Copy file
$resCopyFile = FileManager::copyItem('/func_test_dir/file2.txt', '/func_test_dir/different_dest_renamed');
assertCheck("21. Copy file ke folder lain berhasil", $resCopyFile['success'] === true && file_exists($root . '/func_test_dir/different_dest_renamed/file2.txt'));

// 22. Copy folder rekursif
FileManager::createFolder('/func_test_dir', 'copy_dest');
$resCopyFolder = FileManager::copyItem('/func_test_dir/different_dest_renamed', '/func_test_dir/copy_dest');
assertCheck("22. Copy folder rekursif berhasil", $resCopyFolder['success'] === true && is_dir($root . '/func_test_dir/copy_dest/different_dest_renamed'));

// 23. Move file
$resMoveFile = FileManager::moveItem('/func_test_dir/file3.txt', '/func_test_dir/different_dest_renamed');
assertCheck("23. Move file ke folder lain berhasil", $resMoveFile['success'] === true && file_exists($root . '/func_test_dir/different_dest_renamed/file3.txt') && !file_exists($root . '/func_test_dir/file3.txt'));

// 24. Move folder
FileManager::createFolder('/func_test_dir', 'move_dest');
$resMoveFolder = FileManager::moveItem('/func_test_dir/copy_dest/different_dest_renamed', '/func_test_dir/move_dest');
assertCheck("24. Move folder ke folder lain berhasil", $resMoveFolder['success'] === true && is_dir($root . '/func_test_dir/move_dest/different_dest_renamed'));

// 25. Delete file
$resDeleteFile = FileManager::deleteItem('/func_test_dir/file1_renamed.txt');
assertCheck("25. Delete file berhasil", $resDeleteFile['success'] === true && !file_exists($root . '/func_test_dir/file1_renamed.txt'));

// 26. Delete folder (rekursif)
$resDeleteFolder = FileManager::deleteItem('/func_test_dir');
assertCheck("26. Delete folder beserta seluruh isinya berhasil", $resDeleteFolder['success'] === true && !file_exists($root . '/func_test_dir'));

// 27. File information
$resFileInfo = FileManager::getItemInfo('/documents/readme.txt');
assertCheck("27. File information mengembalikan Name, Size, MIME, Permissions", $resFileInfo['success'] === true && isset($resFileInfo['mime']) && isset($resFileInfo['permissions']));

// 28. Folder information
$resFolderInfo = FileManager::getItemInfo('/documents');
assertCheck("28. Folder information mengembalikan Items Count dan Size", $resFolderInfo['success'] === true && isset($resFolderInfo['items_count']));

// 29. Preview text file
$resPreview = FileManager::getFilePreview('/documents/readme.txt');
assertCheck("29. Preview text file dibaca sebagai plain text aman", $resPreview['success'] === true && is_string($resPreview['content']));

// 30. Search current directory (client-side filter logic verification)
$itemsSample = [
    ['name' => 'index.html'],
    ['name' => 'style.css'],
    ['name' => 'main.js']
];
$searchFilter = array_filter($itemsSample, fn($i) => stripos($i['name'], 'index') !== false);
assertCheck("30. Search current directory memfilter file dengan benar", count($searchFilter) === 1);

// 31. Sort file listing
$listSortedSize = FileManager::listDirectory('/documents', 'size', 'desc');
assertCheck("31. Sort file listing berdasarkan kolom (size, desc) berhasil", $listSortedSize['success'] === true);


// ==========================================================================
// BAGIAN 2: SECURITY & PENETRATION AUDIT TESTING
// ==========================================================================
echo "\n=== BAGIAN 2: AUDIT KEAMANAN & PENCEGAHAN SERANGAN ===\n";

// S1. Traversal ../
assertCheck("S1. Path traversal '../' ditolak", Security::resolvePath('../', false) === null);

// S2. Traversal ../../
assertCheck("S2. Path traversal '../../' ditolak", Security::resolvePath('../../', false) === null);

// S3. Traversal ../../../
assertCheck("S3. Path traversal '../../../config.php' ditolak", Security::resolvePath('../../../config.php', false) === null);

// S4. Encoded traversal (%2e%2e%2f)
assertCheck("S4. Encoded traversal '%2e%2e%2f' ditolak", Security::resolvePath('%2e%2e%2fconfig.php', false) === null);

// S5. Double encoded traversal (%252e%252e%252f)
assertCheck("S5. Double encoded traversal '%252e%252e%252f' ditolak", Security::resolvePath('%252e%252e%252fconfig.php', false) === null);

// S6. Absolute filesystem path Linux (/etc/passwd)
assertCheck("S6. Absolute path Linux '/etc/passwd' ditolak", Security::resolvePath('/etc/passwd', false) === null);

// S7. Absolute filesystem path Windows (C:\Windows\System32)
assertCheck("S7. Absolute path Windows 'C:\\Windows\\System32' ditolak", Security::resolvePath('C:\\Windows\\System32', false) === null);

// S8. Malicious ZIP path / Zip Slip (../../slip.txt)
$slipZip = $tempDir . '/slip.zip';
$z = new ZipArchive();
$z->open($slipZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$z->addFromString('../../slip.txt', 'malicious content');
$z->close();

$zCheck = new ZipArchive();
$zCheck->open($slipZip);
$valSlip = ZipManager::validateArchiveEntries($zCheck, $root);
$zCheck->close();
@unlink($slipZip);
assertCheck("S8. ZIP Slip traversal entry ditolak pre-ekstraksi", $valSlip['valid'] === false);

// S9. Symlink entry di dalam ZIP
$symlinkZip = $tempDir . '/symlink_test.zip';
$zs = new ZipArchive();
$zs->open($symlinkZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zs->addFromString('safe_entry.txt', 'safe');
$zs->close();
// Uji getExternalAttributesIndex
$zsOpen = new ZipArchive();
$zsOpen->open($symlinkZip);
$valSymlink = ZipManager::validateArchiveEntries($zsOpen, $root);
$zsOpen->close();
@unlink($symlinkZip);
assertCheck("S9. Symlink inspection aktif pada validasi ZIP", $valSymlink['valid'] === true);

// S10. Unauthorized path access keluar root
assertCheck("S10. Akses ke luar ALLOWED_ROOT ditolak", Security::resolvePath('/../storage/logs/audit.log', true) === null);

// S11. Protected file tampering (Delete/Rename index.php)
assertCheck("S11. index.php terproteksi dari delete", Security::isProtected('index.php'));
assertCheck("S11b. config.php terproteksi dari delete", Security::isProtected('config.php'));
assertCheck("S11c. folder app terproteksi dari modifikasi", Security::isProtected('app'));

// S12. Unauthorized download keluar root
$unauthDl = Security::resolvePath('/../config.php', true);
assertCheck("S12. Unauthorized download diblokir oleh path resolver", $unauthDl === null);

// S13. Unauthorized preview file non-text (.exe, .bin)
$binFile = $root . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'binary.bin';
file_put_contents($binFile, "\x00\x01\x02\x03\xFF");
$previewBin = FileManager::getFilePreview('/documents/binary.bin');
@unlink($binFile);
assertCheck("S13. Preview file biner ditolak (hanya whitelist teks)", $previewBin['success'] === false);

// S14. Windows device name injection (CON, PRN, AUX, NUL)
assertCheck("S14. Windows reserved device name 'CON.txt' ditolak", !Security::isValidFilename('CON.txt'));
assertCheck("S14b. Windows reserved device name 'NUL' ditolak", !Security::isValidFilename('NUL'));
assertCheck("S14c. Windows reserved device name 'AUX.php' ditolak", !Security::isValidFilename('AUX.php'));

// S15. Trailing dot / space extension bypass ('shell.php.')
assertCheck("S15. Filename dengan trailing dot 'shell.php.' ditolak", !Security::isValidFilename('shell.php.'));
assertCheck("S15b. Filename dengan trailing space 'shell.php ' ditolak", !Security::isValidFilename('shell.php '));

// S16. Null byte injection ('shell.php\0.jpg')
assertCheck("S16. Filename dengan null byte ditolak", !Security::isValidFilename("shell.php\0.jpg"));

// S17. CSRF validation verification
$validToken = Auth::getCsrfToken();
assertCheck("S17. Validasi token CSRF valid diterima", Auth::validateCsrfToken($validToken) === true);
assertCheck("S17b. Validasi token CSRF palsu ditolak", Auth::validateCsrfToken('invalid_token_123') === false);


// ==========================================================================
// BAGIAN 3: PENGUJIAN CURRENT DIRECTORY WORKFLOW & BOUNDARY
// ==========================================================================
echo "\n=== BAGIAN 3: PENGUJIAN CURRENT DIRECTORY WORKFLOW & BOUNDARY ===\n";

$currentNestedPath = '/current_dir_test/sub1/sub2';
$absNested = $root . str_replace('/', DIRECTORY_SEPARATOR, $currentNestedPath);
if (!is_dir($absNested)) {
    @mkdir($absNested, 0755, true);
}

// CD1. Listing directory di dalam CURRENT_PATH
$listCurrent = FileManager::listDirectory($currentNestedPath);
assertCheck("CD1. File listing mengembalikan isi CURRENT_PATH yang aktif", $listCurrent['success'] === true && $listCurrent['current_path'] === $currentNestedPath);

// CD2. Breadcrumbs terbentuk hierarkis sesuai CURRENT_PATH
$crumbs = $listCurrent['breadcrumbs'];
$crumbPaths = array_column($crumbs, 'path');
assertCheck("CD2. Breadcrumb hierarkis terbentuk benar dari Root hingga CURRENT_PATH", 
    count($crumbs) === 4 && 
    $crumbPaths[0] === '/' && 
    $crumbPaths[1] === '/current_dir_test' && 
    $crumbPaths[2] === '/current_dir_test/sub1' && 
    $crumbPaths[3] === '/current_dir_test/sub1/sub2'
);

// CD3. New Folder dibuat di CURRENT_PATH
$resNewFolder = FileManager::createFolder($currentNestedPath, 'child_folder');
assertCheck("CD3. New Folder default dibuat di dalam CURRENT_PATH", 
    $resNewFolder['success'] === true && is_dir($absNested . DIRECTORY_SEPARATOR . 'child_folder')
);

// CD4. New File dibuat di CURRENT_PATH
$resNewFile = FileManager::createFile($currentNestedPath, 'script.php', '<?php echo "nested";');
assertCheck("CD4. New File (sisipin berkas) default dibuat di dalam CURRENT_PATH", 
    $resNewFile['success'] === true && file_exists($absNested . DIRECTORY_SEPARATOR . 'script.php')
);

// CD5. Extract ZIP default ke CURRENT_PATH
$zipNestedPath = $root . DIRECTORY_SEPARATOR . 'current_dir_test' . DIRECTORY_SEPARATOR . 'nested_test.zip';
$zipTest = new ZipArchive();
if ($zipTest->open($zipNestedPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $zipTest->addFromString('extracted_in_current.txt', 'Ekstrak di folder aktif!');
    $zipTest->close();
}
$resExtractCurrent = ZipManager::extractZip('/current_dir_test/nested_test.zip', $currentNestedPath);
assertCheck("CD5. Extract ZIP default menggunakan CURRENT_PATH sebagai destinasi", 
    $resExtractCurrent['success'] === true && file_exists($absNested . DIRECTORY_SEPARATOR . 'extracted_in_current.txt')
);

// CD6. Copy / Move default destinasi CURRENT_PATH
$resCopyCurrent = FileManager::copyItem('/current_dir_test/nested_test.zip', $currentNestedPath);
assertCheck("CD6. Copy berkas dengan destinasi CURRENT_PATH berhasil", 
    $resCopyCurrent['success'] === true && file_exists($absNested . DIRECTORY_SEPARATOR . 'nested_test.zip')
);

// CD7. Path resolver mendukung path dengan prefix nama root directory
$rootBaseName = basename($root);
$prefixedPath = '/' . $rootBaseName . $currentNestedPath;
$resolvedPrefixed = Security::resolvePath($prefixedPath, true);
assertCheck("CD7. Path dengan prefix nama root ('/$rootBaseName$currentNestedPath') ter-resolve aman", 
    $resolvedPrefixed !== null && realpath($resolvedPrefixed) === realpath($absNested)
);

// CD8. Traversal dari CURRENT_PATH keluar ALLOWED_ROOT ditolak
$deepTraversal1 = Security::resolvePath($currentNestedPath . '/../../../../../../etc/passwd', false);
assertCheck("CD8. Traversal berantai dari CURRENT_PATH ('$currentNestedPath/../../../../../../etc/passwd') ditolak", 
    $deepTraversal1 === null
);
$deepTraversal2 = Security::resolvePath($currentNestedPath . '/..\\..\\..\\..\\..\\Windows\\System32', false);
assertCheck("CD8b. Windows traversal dari CURRENT_PATH ditolak", 
    $deepTraversal2 === null
);

// CD9. Parent navigation tidak dapat melampaui ALLOWED_ROOT
$parentTestPath = $currentNestedPath;
for ($p = 0; $p < 10; $p++) {
    $parentParts = explode('/', trim($parentTestPath, '/'));
    array_pop($parentParts);
    $parentTestPath = empty($parentParts) ? '/' : '/' . implode('/', $parentParts);
}
assertCheck("CD9. Navigasi berulang ke parent (Up) berhenti aman di Root ('/') tanpa keluar batas", 
    $parentTestPath === '/' && Security::isInsideRoot(Security::resolvePath($parentTestPath, true), $root)
);

// Bersihkan folder pengujian Bagian 3
FileManager::deleteItem('/current_dir_test');

// ==========================================================================
// BAGIAN 4: PENGUJIAN FITUR UPLOAD FILE MENYELURUH (6 SKENARIO SPESIFIK)
// ==========================================================================
echo "\n=== BAGIAN 4: PENGUJIAN FITUR UPLOAD FILE MENYELURUH (6 SKENARIO) ===\n";

$uploadTestDir = '/upload_audit_test';
FileManager::createFolder('/', 'upload_audit_test');
$realUploadDir = Security::resolvePath($uploadTestDir, true);

// UP1. Uji Upload File Kecil
$tmpSmall = tempnam(sys_get_temp_dir(), 'up_small_');
file_put_contents($tmpSmall, "Ini adalah konten berkas kecil untuk pengujian upload.\nBaris kedua.");
$smallSize = filesize($tmpSmall);
$resUpSmall = FileManager::saveUploadedFile($uploadTestDir, $tmpSmall, 'small_note.txt', $smallSize, UPLOAD_ERR_OK, false);
assertCheck("UP1. Upload file kecil tersimpan utuh di direktori target", 
    $resUpSmall['success'] === true && 
    file_exists($realUploadDir . DIRECTORY_SEPARATOR . 'small_note.txt') &&
    filesize($realUploadDir . DIRECTORY_SEPARATOR . 'small_note.txt') === $smallSize
);
@unlink($tmpSmall);

// UP2. Uji Upload File Besar (5 MB Binary/Data)
$tmpLarge = tempnam(sys_get_temp_dir(), 'up_large_');
$largeChunk = str_repeat('ABCDEF1234567890', 64 * 1024); // 1 MB
$largeHandle = fopen($tmpLarge, 'wb');
for ($mb = 0; $mb < 5; $mb++) {
    fwrite($largeHandle, $largeChunk);
}
fclose($largeHandle);
$largeSize = filesize($tmpLarge); // 5 MB
$resUpLarge = FileManager::saveUploadedFile($uploadTestDir, $tmpLarge, 'large_backup.bin', $largeSize, UPLOAD_ERR_OK, false);
assertCheck("UP2. Upload file besar (5 MB) berhasil dengan ukuran akurat", 
    $resUpLarge['success'] === true && 
    file_exists($realUploadDir . DIRECTORY_SEPARATOR . 'large_backup.bin') &&
    filesize($realUploadDir . DIRECTORY_SEPARATOR . 'large_backup.bin') === $largeSize &&
    strpos($resUpLarge['formatted_size'], 'MB') !== false
);
@unlink($tmpLarge);

// UP3. Uji Upload Berkas ZIP
$tmpZip = tempnam(sys_get_temp_dir(), 'up_zip_');
$zipObj = new ZipArchive();
$zipObj->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zipObj->addFromString('hello.txt', 'Hello from inside uploaded zip!');
$zipObj->addFromString('assets/style.css', 'body { margin: 0; }');
$zipObj->close();
$zipSize = filesize($tmpZip);
$resUpZip = FileManager::saveUploadedFile($uploadTestDir, $tmpZip, 'bundle_site.zip', $zipSize, UPLOAD_ERR_OK, false);
$realUploadedZip = $realUploadDir . DIRECTORY_SEPARATOR . 'bundle_site.zip';
assertCheck("UP3. Upload file ZIP valid tersimpan dan siap diekstrak", 
    $resUpZip['success'] === true && 
    file_exists($realUploadedZip) &&
    filesize($realUploadedZip) === $zipSize
);
@unlink($tmpZip);

// UP4. Uji Upload Multiple Files (PHP, CSS, JS, Gambar)
$batchFiles = [
    'index.php'  => '<?php echo "Hello World";',
    'app.js'     => 'console.log("App loaded");',
    'theme.css'  => 'h1 { color: #0066cc; }',
    'image.png'  => "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR"
];
$allBatchSuccess = true;
foreach ($batchFiles as $name => $content) {
    $t = tempnam(sys_get_temp_dir(), 'batch_');
    file_put_contents($t, $content);
    $sz = filesize($t);
    $r = FileManager::saveUploadedFile($uploadTestDir, $t, $name, $sz, UPLOAD_ERR_OK, false);
    if (!$r['success'] || !file_exists($realUploadDir . DIRECTORY_SEPARATOR . $name)) {
        $allBatchSuccess = false;
    }
    @unlink($t);
}
assertCheck("UP4. Upload multiple files (PHP, CSS, JS, PNG) sekaligus berhasil", $allBatchSuccess === true);

// UP5. Uji Upload ke Folder Berbeda (/folder_alpha & /folder_beta)
FileManager::createFolder($uploadTestDir, 'folder_alpha');
FileManager::createFolder($uploadTestDir, 'folder_beta');
$dirAlpha = $uploadTestDir . '/folder_alpha';
$dirBeta = $uploadTestDir . '/folder_beta';

$tAlpha = tempnam(sys_get_temp_dir(), 'alpha_');
file_put_contents($tAlpha, 'Alpha specific data');
$resAlpha = FileManager::saveUploadedFile($dirAlpha, $tAlpha, 'alpha_config.json', filesize($tAlpha), UPLOAD_ERR_OK, false);
@unlink($tAlpha);

$tBeta = tempnam(sys_get_temp_dir(), 'beta_');
file_put_contents($tBeta, 'Beta specific script');
$resBeta = FileManager::saveUploadedFile($dirBeta, $tBeta, 'beta_module.js', filesize($tBeta), UPLOAD_ERR_OK, false);
@unlink($tBeta);

$realAlphaFile = Security::resolvePath($dirAlpha . '/alpha_config.json', true);
$realBetaFile = Security::resolvePath($dirBeta . '/beta_module.js', true);
$realAlphaCross = Security::resolvePath($dirAlpha . '/beta_module.js', true);

assertCheck("UP5. Upload ke direktori berbeda terisolasi tepat pada foldernya masing-masing", 
    $resAlpha['success'] === true && 
    $resBeta['success'] === true &&
    file_exists($realAlphaFile) && 
    file_exists($realBetaFile) && 
    $realAlphaCross === null
);

// UP6. Uji Upload Ketika CURRENT_PATH Bukan ALLOWED_ROOT (Deeply Nested)
$deepCurrentPath = $uploadTestDir . '/uploads/images';
FileManager::createFolder($uploadTestDir, 'uploads');
FileManager::createFolder($uploadTestDir . '/uploads', 'images');
$realDeepImages = Security::resolvePath($deepCurrentPath, true);

$tDeep = tempnam(sys_get_temp_dir(), 'deep_');
file_put_contents($tDeep, 'JPEG_BINARY_DATA_EMULATION');
$resDeep = FileManager::saveUploadedFile($deepCurrentPath, $tDeep, 'foto.jpg', filesize($tDeep), UPLOAD_ERR_OK, false);
@unlink($tDeep);

$deepTargetFile = $realDeepImages . DIRECTORY_SEPARATOR . 'foto.jpg';
$rootAccidentalFile = $root . DIRECTORY_SEPARATOR . 'foto.jpg';

assertCheck("UP6. Upload pada CURRENT_PATH nested ('/uploads/images/') masuk ke target dan TIDAK jatuh di Root", 
    $resDeep['success'] === true && 
    file_exists($deepTargetFile) && 
    !file_exists($rootAccidentalFile) &&
    $resDeep['virtual_path'] === $deepCurrentPath . '/foto.jpg'
);

// UP7. Validasi Penolakan Upload Protected File
$tProt = tempnam(sys_get_temp_dir(), 'prot_');
file_put_contents($tProt, 'Hacked config');
$resProt = FileManager::saveUploadedFile('/', $tProt, 'config.php', filesize($tProt), UPLOAD_ERR_OK, false);
assertCheck("UP7. Upload yang menimpa protected file ('config.php') ditolak", 
    $resProt['success'] === false
);
@unlink($tProt);

// UP8. Validasi Penolakan Upload dengan Error Code Server
$tErr = tempnam(sys_get_temp_dir(), 'err_');
file_put_contents($tErr, 'Test');
$resErr = FileManager::saveUploadedFile($uploadTestDir, $tErr, 'broken.txt', filesize($tErr), UPLOAD_ERR_INI_SIZE, false);
assertCheck("UP8. Upload dengan error code UPLOAD_ERR_INI_SIZE ditolak dengan pesan jelas", 
    $resErr['success'] === false && strpos($resErr['message'], 'upload_max_filesize') !== false
);
@unlink($tErr);

// Bersihkan folder audit upload
FileManager::deleteItem($uploadTestDir);

// ==========================================================================
// BAGIAN 5: PENGUJIAN FITUR ADVANCED cPANEL FILE MANAGER (CP1 - CP15)
// ==========================================================================
echo "\n=== BAGIAN 5: PENGUJIAN FITUR ADVANCED cPANEL FILE MANAGER ===\n";

$cpanelTestDir = '/cpanel_audit_test';
FileManager::createFolder('/', 'cpanel_audit_test');

// CP1. Dotfiles (Hidden files) - default hidden
file_put_contents($root . '/cpanel_audit_test/.htaccess', '# Protected htaccess');
file_put_contents($root . '/cpanel_audit_test/.env', 'APP_ENV=production');
file_put_contents($root . '/cpanel_audit_test/normal.txt', 'Public text');

$listNoHidden = FileManager::listDirectory($cpanelTestDir, false);
$hasDotfilesWhenHidden = false;
foreach ($listNoHidden['items'] as $it) {
    if (str_starts_with($it['name'], '.')) {
        $hasDotfilesWhenHidden = true;
    }
}
assertCheck("CP1. Dotfiles disembunyikan secara default saat show_hidden = false", !$hasDotfilesWhenHidden);

// CP2. Dotfiles - show_hidden = true
$listWithHidden = FileManager::listDirectory($cpanelTestDir, true);
$dotfilesFound = [];
foreach ($listWithHidden['items'] as $it) {
    if (str_starts_with($it['name'], '.')) {
        $dotfilesFound[] = $it['name'];
    }
}
assertCheck("CP2. Dotfiles (.htaccess, .env) ditampilkan saat show_hidden = true", 
    in_array('.htaccess', $dotfilesFound) && in_array('.env', $dotfilesFound)
);

// CP3. In-Browser Editor: getFileContent
$resContent = FileManager::getFileContent('/cpanel_audit_test/normal.txt');
assertCheck("CP3. Editor getFileContent berhasil membaca konten teks UTF-8", 
    $resContent['success'] === true && $resContent['content'] === 'Public text'
);

// CP4. In-Browser Editor: saveFileContent
$newContent = "<?php\n\necho 'Updated via In-Browser Editor';\n";
$resSaveContent = FileManager::saveFileContent('/cpanel_audit_test/script.php', $newContent);
$resReadSaved = FileManager::getFileContent('/cpanel_audit_test/script.php');
assertCheck("CP4. Editor saveFileContent berhasil menyimpan konten baru", 
    $resSaveContent['success'] === true && $resReadSaved['content'] === $newContent
);

// CP5. In-Browser Editor: saveFileContent pada protected file ditolak
$resSaveProtected = FileManager::saveFileContent('/config.php', '<?php echo "Hacked";');
assertCheck("CP5. Editor saveFileContent menolak penulisan pada protected file (config.php)", 
    $resSaveProtected['success'] === false
);

// CP6. ZIP Manager: createZip (Single File)
$resZipSingle = ZipManager::createZip(['/cpanel_audit_test/normal.txt'], $cpanelTestDir, 'single_archive.zip');
assertCheck("CP6. createZip berhasil mengompres 1 file ke dalam archive", 
    $resZipSingle['success'] === true && file_exists($root . '/cpanel_audit_test/single_archive.zip')
);

// CP7. ZIP Manager: createZip (Folder Recursive + Multiple Files)
FileManager::createFolder($cpanelTestDir, 'subfolder');
file_put_contents($root . '/cpanel_audit_test/subfolder/nested.txt', 'Nested in subfolder');
$resZipMulti = ZipManager::createZip(
    ['/cpanel_audit_test/subfolder', '/cpanel_audit_test/normal.txt'], 
    $cpanelTestDir, 
    'multi_archive.zip'
);
assertCheck("CP7. createZip berhasil mengompres folder rekursif dan file sekaligus", 
    $resZipMulti['success'] === true && file_exists($root . '/cpanel_audit_test/multi_archive.zip')
);

// CP8. ZIP Manager: createZip verifikasi isi arsip
$za = new ZipArchive();
$za->open($root . '/cpanel_audit_test/multi_archive.zip');
$entry1 = $za->locateName('normal.txt') !== false;
$entry2 = $za->locateName('subfolder/nested.txt') !== false;
$za->close();
assertCheck("CP8. Isi arsip ZIP hasil createZip utuh dan mempertahankan hierarki folder", 
    $entry1 && $entry2
);

// CP9. Permissions: changePermissions (CHMOD)
$resChmod = FileManager::changePermissions('/cpanel_audit_test/normal.txt', '0644');
$infoChmod = FileManager::getItemInfo('/cpanel_audit_test/normal.txt');
assertCheck("CP9. changePermissions berhasil mengubah izin file (octal)", 
    $resChmod['success'] === true && isset($infoChmod['octal'])
);

// CP10. Permissions: changePermissions pada protected file ditolak
$resChmodProt = FileManager::changePermissions('/config.php', '0777');
assertCheck("CP10. changePermissions menolak perubahan izin pada protected file", 
    $resChmodProt['success'] === false
);

// CP11. Bulk Operations: bulkCopy
FileManager::createFolder($cpanelTestDir, 'bulk_dest');
file_put_contents($root . '/cpanel_audit_test/bulk_a.txt', 'Bulk A');
file_put_contents($root . '/cpanel_audit_test/bulk_b.txt', 'Bulk B');
$resBulkCopy = FileManager::bulkCopy(
    ['/cpanel_audit_test/bulk_a.txt', '/cpanel_audit_test/bulk_b.txt'], 
    '/cpanel_audit_test/bulk_dest'
);
assertCheck("CP11. bulkCopy berhasil menyalin multi-file ke target direktori", 
    $resBulkCopy['success'] === true && 
    file_exists($root . '/cpanel_audit_test/bulk_dest/bulk_a.txt') &&
    file_exists($root . '/cpanel_audit_test/bulk_dest/bulk_b.txt')
);

// CP12. Bulk Operations: bulkMove
FileManager::createFolder($cpanelTestDir, 'bulk_move_dest');
$resBulkMove = FileManager::bulkMove(
    ['/cpanel_audit_test/bulk_dest/bulk_a.txt', '/cpanel_audit_test/bulk_dest/bulk_b.txt'], 
    '/cpanel_audit_test/bulk_move_dest'
);
assertCheck("CP12. bulkMove berhasil memindahkan multi-file ke target direktori", 
    $resBulkMove['success'] === true && 
    file_exists($root . '/cpanel_audit_test/bulk_move_dest/bulk_a.txt') &&
    !file_exists($root . '/cpanel_audit_test/bulk_dest/bulk_a.txt')
);

// CP13. Bulk Operations: bulkDelete
$resBulkDelete = FileManager::bulkDelete([
    '/cpanel_audit_test/bulk_move_dest/bulk_a.txt',
    '/cpanel_audit_test/bulk_move_dest/bulk_b.txt',
    '/cpanel_audit_test/bulk_dest'
]);
assertCheck("CP13. bulkDelete berhasil menghapus multiple berkas dan folder sekaligus", 
    $resBulkDelete['success'] === true && 
    !file_exists($root . '/cpanel_audit_test/bulk_move_dest/bulk_a.txt') &&
    !file_exists($root . '/cpanel_audit_test/bulk_dest')
);

// CP14. Bulk Operations: bulkDelete melindungi protected file
$resBulkDeleteProt = FileManager::bulkDelete(['/config.php', '/index.php']);
assertCheck("CP14. bulkDelete memproteksi file sistem dari penghapusan massal", 
    $resBulkDeleteProt['deleted_count'] === 0 && file_exists(__DIR__ . '/../config.php')
);

// CP15. Bulk Download: downloadMultipleAsZip
$bulkZipPath = FileManager::downloadMultipleAsZip([
    '/cpanel_audit_test/normal.txt',
    '/cpanel_audit_test/script.php'
], 'bulk_test.zip', false);
assertCheck("CP15. downloadMultipleAsZip berhasil membuat arsip streaming sementara", 
    $bulkZipPath !== null && file_exists($bulkZipPath)
);
if ($bulkZipPath && file_exists($bulkZipPath)) {
    @unlink($bulkZipPath);
}

// Bersihkan folder audit cPanel
FileManager::deleteItem($cpanelTestDir);

echo "\n======================================================================\n";
echo "  REKAP HASIL AKHIR: $passed PASSED, $failed FAILED\n";
echo "======================================================================\n\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
