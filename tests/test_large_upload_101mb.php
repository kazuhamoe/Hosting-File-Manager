<?php
/**
 * Test Suite: Verifikasi Komprehensif Upload File Besar (10 MB, 50 MB, 101.31 MB AGM-SaatIni.zip)
 * Menguji integrasi end-to-end via HTTP cURL terhadap Apache (Port 8080).
 */

set_time_limit(600);
ini_set('memory_limit', '1024M');

$baseUrl = 'http://localhost:8080/File-Upload/index.php';
$cookieFile = tempnam(sys_get_temp_dir(), 'ck_test_');

echo "======================================================================\n";
echo "  TEST SUITE: END-TO-END LARGE FILE UPLOAD (10MB, 50MB, 101.31MB)\n";
echo "======================================================================\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 300);

function report($id, $name, $pass, $detail = '') {
    $badge = $pass ? "[PASS]" : "[FAIL]";
    echo sprintf("%s %2d. %-35s %s\n", $badge, $id, $name, $detail);
    if (!$pass) {
        echo "   [ERROR DETAIL] $detail\n";
    }
}

// 1. Dapatkan Halaman Login & CSRF Awal
curl_setopt($ch, CURLOPT_URL, $baseUrl);
curl_setopt($ch, CURLOPT_HTTPGET, true);
$html = curl_exec($ch);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m);
$loginCsrf = $m[1] ?? '';

// 2. Login
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'username' => 'admin',
    'password' => 'Admin#12345',
    'csrf_token' => $loginCsrf
]));
curl_exec($ch);

// 3. Dapatkan CSRF Token Sesi Terotentikasi
curl_setopt($ch, CURLOPT_URL, $baseUrl);
curl_setopt($ch, CURLOPT_HTTPGET, true);
$mainHtml = curl_exec($ch);
preg_match('/content="([a-f0-9]{64})"/', $mainHtml, $mAuth);
$csrfToken = $mAuth[1] ?? '';
report(1, 'Autentikasi & CSRF Token', !empty($csrfToken), 'Sesi aktif dan CSRF token terverifikasi');

// 4. Periksa Endpoint upload_limits
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=upload_limits');
curl_setopt($ch, CURLOPT_HTTPGET, true);
$limitsRaw = curl_exec($ch);
$limitsData = json_decode($limitsRaw, true);
$can101 = $limitsData['limits']['can_upload_101mb'] ?? false;
$postMax = $limitsData['limits']['post_max_size'] ?? '';
$uploadMax = $limitsData['limits']['upload_max_filesize'] ?? '';
report(2, 'Server Upload Limits Diagnostic', $can101, "post_max_size: $postMax, upload_max_filesize: $uploadMax");

// Buat folder target pengujian di server: /test_large_dir
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=mkdir');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'csrf_token' => $csrfToken,
    'target_dir' => '/',
    'folder_name' => 'test_large_dir'
]);
curl_exec($ch);

// TEST 1: File Kecil (50 KB)
$smallPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'small_doc.txt';
file_put_contents($smallPath, str_repeat("Hosting File Manager Test Line.\n", 1500));
$smallSize = filesize($smallPath);

curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=upload&target_dir=/test_large_dir');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'csrf_token' => $csrfToken,
    'target_dir' => '/test_large_dir',
    'file' => new CURLFile($smallPath, 'text/plain', 'small_doc.txt')
]);
$resSmallRaw = curl_exec($ch);
$resSmall = json_decode($resSmallRaw, true);
@unlink($smallPath);
report(3, 'Upload File Kecil (50 KB)', !empty($resSmall['success']), 'Response valid JSON: ' . ($resSmall['message'] ?? $resSmallRaw));

// TEST 2: File 10 MB
$mb10Path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'data_10mb.dat';
$fp10 = fopen($mb10Path, 'wb');
for ($i = 0; $i < 10; $i++) {
    fwrite($fp10, str_repeat('X', 1024 * 1024));
}
fclose($fp10);
$mb10Size = filesize($mb10Path);

curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=upload&target_dir=/test_large_dir');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'csrf_token' => $csrfToken,
    'target_dir' => '/test_large_dir',
    'file' => new CURLFile($mb10Path, 'application/octet-stream', 'data_10mb.dat')
]);
$res10Raw = curl_exec($ch);
$res10 = json_decode($res10Raw, true);
@unlink($mb10Path);
report(4, 'Upload File 10 MB', !empty($res10['success']), 'Tersimpan utuh (10 MB): ' . ($res10['message'] ?? $res10Raw));

// TEST 3: File ZIP 50 MB
$zip50Path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'archive_50mb.zip';
$za50 = new ZipArchive();
$za50->open($zip50Path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
for ($i = 0; $i < 5; $i++) {
    $za50->addFromString("content_$i.bin", str_repeat(chr(65 + $i), 10 * 1024 * 1024));
    $za50->setCompressionName("content_$i.bin", ZipArchive::CM_STORE);
}
$za50->close();
$zip50Size = filesize($zip50Path);

curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=upload&target_dir=/test_large_dir');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'csrf_token' => $csrfToken,
    'target_dir' => '/test_large_dir',
    'file' => new CURLFile($zip50Path, 'application/zip', 'archive_50mb.zip')
]);
$res50Raw = curl_exec($ch);
$res50 = json_decode($res50Raw, true);
@unlink($zip50Path);
report(5, 'Upload File ZIP 50 MB', !empty($res50['success']), 'Tersimpan utuh (50 MB): ' . ($res50['message'] ?? $res50Raw));

// TEST 4: File ZIP 101.31 MB (AGM-SaatIni.zip)
$targetBytes101 = (int)round(101.31 * 1024 * 1024);
$zip101Path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'AGM-SaatIni.zip';
$za101 = new ZipArchive();
$za101->open($zip101Path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
// Masukkan data agar mencapai sekitar 101.31 MB
for ($i = 0; $i < 10; $i++) {
    $za101->addFromString("part_$i.dat", str_repeat(chr(70 + $i), 10 * 1024 * 1024));
    $za101->setCompressionName("part_$i.dat", ZipArchive::CM_STORE);
}
$za101->addFromString("tail.dat", str_repeat('Z', 1373634));
$za101->setCompressionName("tail.dat", ZipArchive::CM_STORE);
$za101->close();
$actual101Size = filesize($zip101Path);

echo "   -> Ukuran berkas uji AGM-SaatIni.zip: " . number_format($actual101Size / (1024*1024), 2) . " MB ($actual101Size bytes)\n";

curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=upload&target_dir=/test_large_dir');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'csrf_token' => $csrfToken,
    'target_dir' => '/test_large_dir',
    'file' => new CURLFile($zip101Path, 'application/zip', 'AGM-SaatIni.zip')
]);
$res101Raw = curl_exec($ch);
$httpCode101 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$res101 = json_decode($res101Raw, true);
@unlink($zip101Path);

$isJson101 = is_array($res101);
$pass101 = $isJson101 && ($res101['success'] === true);
report(6, 'Upload AGM-SaatIni.zip (101.31 MB)', $pass101, "HTTP $httpCode101, JSON Valid: " . ($isJson101 ? 'YES' : 'NO') . ", Res: " . ($res101['message'] ?? substr($res101Raw, 0, 150)));

// TEST 5: Cek Verifikasi File di Server (check_file API)
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=check_file&dir=/test_large_dir&name=AGM-SaatIni.zip&size=' . $actual101Size);
curl_setopt($ch, CURLOPT_HTTPGET, true);
$checkRaw = curl_exec($ch);
$checkData = json_decode($checkRaw, true);
$checkPass = !empty($checkData['exists']) && !empty($checkData['matches_size']);
report(7, 'Verifikasi Integritas Disk (check_file)', $checkPass, 'File ada di disk dengan ukuran pas: ' . ($checkData['size_human'] ?? 'tidak cocok'));

// TEST 6: Upload Multiple Files Sekaligus
$m1 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'site.css';
$m2 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'app.js';
file_put_contents($m1, 'body { color: blue; }');
file_put_contents($m2, 'console.log("hello");');

curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=upload&target_dir=/test_large_dir');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'csrf_token' => $csrfToken,
    'target_dir' => '/test_large_dir',
    'file1' => new CURLFile($m1, 'text/css', 'site.css'),
    'file2' => new CURLFile($m2, 'application/javascript', 'app.js')
]);
$resMultiRaw = curl_exec($ch);
$resMulti = json_decode($resMultiRaw, true);
@unlink($m1);
@unlink($m2);
report(8, 'Upload Multiple Files Sekaligus', !empty($resMulti['success']) && ($resMulti['uploaded_count'] ?? 0) === 2, '2 berkas terunggah bersamaan');

// TEST 7: Upload ke Nested Folder Berbeda
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=mkdir');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'csrf_token' => $csrfToken,
    'target_dir' => '/test_large_dir',
    'folder_name' => 'nested_sub'
]);
curl_exec($ch);

$nestedDoc = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nested_file.txt';
file_put_contents($nestedDoc, 'nested content');
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=upload&target_dir=/test_large_dir/nested_sub');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'csrf_token' => $csrfToken,
    'target_dir' => '/test_large_dir/nested_sub',
    'file' => new CURLFile($nestedDoc, 'text/plain', 'nested_file.txt')
]);
$resNestedRaw = curl_exec($ch);
$resNested = json_decode($resNestedRaw, true);
@unlink($nestedDoc);
report(9, 'Upload ke Nested CURRENT_PATH', !empty($resNested['success']) && ($resNested['target_dir'] ?? '') === '/test_large_dir/nested_sub', 'Tersimpan tepat di CURRENT_PATH nested');

// TEST 8: Verifikasi List Directory
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=list&path=/test_large_dir');
curl_setopt($ch, CURLOPT_HTTPGET, true);
$listRaw = curl_exec($ch);
$listData = json_decode($listRaw, true);
$foundAgm = false;
foreach ($listData['items'] ?? [] as $it) {
    if ($it['name'] === 'AGM-SaatIni.zip' && $it['size_raw'] === $actual101Size) {
        $foundAgm = true;
        break;
    }
}
report(10, 'List Directory Menampilkan AGM-SaatIni.zip', $foundAgm, "Item terdaftar dengan ukuran {$actual101Size} bytes");

// Cleanup folder pengujian
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=delete');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'csrf_token' => $csrfToken,
    'path' => '/test_large_dir'
]);
curl_exec($ch);
@unlink($cookieFile);

echo "\n======================================================================\n";
echo "  PENGUJIAN SELESAI\n";
echo "======================================================================\n";

