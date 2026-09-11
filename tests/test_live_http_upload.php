<?php
/**
 * Live HTTP Upload Integration Test
 */

$cookieFile = tempnam(sys_get_temp_dir(), 'ck_');
$baseUrl = 'http://localhost:8080/File-Upload/index.php';

echo "1. Mengambil halaman login...\n";
$ch = curl_init($baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$html = curl_exec($ch);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m);
$csrf = $m[1] ?? '';
echo "Initial CSRF: " . ($csrf ? 'Found' : 'Missing') . "\n";

echo "2. Melakukan login...\n";
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'username' => 'admin',
    'password' => 'Admin#12345',
    'csrf_token' => $csrf
]));
$loginRes = curl_exec($ch);
echo "Login Response: $loginRes\n";

echo "3. Mengambil CSRF token terotentikasi...\n";
curl_setopt($ch, CURLOPT_URL, $baseUrl);
curl_setopt($ch, CURLOPT_HTTPGET, true);
$mainHtml = curl_exec($ch);
preg_match('/content="([a-f0-9]{64})"/', $mainHtml, $m2);
$authCsrf = $m2[1] ?? '';
echo "Auth CSRF: " . ($authCsrf ? 'Found' : 'Missing') . "\n";

echo "4. Membuat folder target /http_test_upload...\n";
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=mkdir');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'target_dir' => '/',
    'folder_name' => 'http_test_upload',
    'csrf_token' => $authCsrf
]));
$mkdirRes = curl_exec($ch);
echo "Mkdir: $mkdirRes\n";

echo "5. Uji Live Upload Single File (small_doc.txt)...\n";
$tmpSmall = tempnam(sys_get_temp_dir(), 'http_up_');
file_put_contents($tmpSmall, 'Halo dari live HTTP upload test!');
$cFileSmall = new CURLFile($tmpSmall, 'text/plain', 'small_doc.txt');

curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=upload');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'csrf_token' => $authCsrf,
    'target_dir' => '/http_test_upload',
    'file' => $cFileSmall
]);
$upResSmall = curl_exec($ch);
echo "Upload Small File: $upResSmall\n";
@unlink($tmpSmall);

echo "6. Membuat subfolder nested /http_test_upload/nested_images...\n";
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=mkdir');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'target_dir' => '/http_test_upload',
    'folder_name' => 'nested_images',
    'csrf_token' => $authCsrf
]));
curl_exec($ch);

echo "7. Uji Live Upload ke Nested CURRENT_PATH (/http_test_upload/nested_images/photo.png)...\n";
$tmpImg = tempnam(sys_get_temp_dir(), 'http_img_');
file_put_contents($tmpImg, "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR");
$cFileImg = new CURLFile($tmpImg, 'image/png', 'photo.png');

curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=upload');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'csrf_token' => $authCsrf,
    'target_dir' => '/http_test_upload/nested_images',
    'file' => $cFileImg
]);
$upResNested = curl_exec($ch);
echo "Upload Nested File: $upResNested\n";
@unlink($tmpImg);

echo "8. Uji Live Upload File ZIP (archive.zip)...\n";
$tmpZip = tempnam(sys_get_temp_dir(), 'http_zip_');
$za = new ZipArchive();
$za->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$za->addFromString('readme.txt', 'Readme inside archive');
$za->close();
$cFileZip = new CURLFile($tmpZip, 'application/zip', 'archive.zip');

curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=upload');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'csrf_token' => $authCsrf,
    'target_dir' => '/http_test_upload',
    'file' => $cFileZip
]);
$upResZip = curl_exec($ch);
echo "Upload ZIP: $upResZip\n";
@unlink($tmpZip);

echo "9. Mengambil isi direktori /http_test_upload via AJAX list...\n";
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=list&path=' . urlencode('/http_test_upload'));
curl_setopt($ch, CURLOPT_HTTPGET, true);
$listRes = curl_exec($ch);
echo "List Response: $listRes\n";

echo "10. Mengambil isi nested /http_test_upload/nested_images via AJAX list...\n";
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=list&path=' . urlencode('/http_test_upload/nested_images'));
curl_setopt($ch, CURLOPT_HTTPGET, true);
$nestedListRes = curl_exec($ch);
echo "Nested List: $nestedListRes\n";

echo "11. Membersihkan folder pengujian /http_test_upload...\n";
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=delete');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $authCsrf,
    'path' => '/http_test_upload'
]));
$delRes = curl_exec($ch);
echo "Cleanup: $delRes\n";

curl_close($ch);
@unlink($cookieFile);
echo "\n=== SEMUA PENGUJIAN HTTP LIVE SELESAI ===\n";
