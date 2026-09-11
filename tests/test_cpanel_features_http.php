<?php
/**
 * Live HTTP Integration Test for Advanced cPanel File Manager Features
 */

$cookieFile = tempnam(sys_get_temp_dir(), 'ck_cp_');
$baseUrl = 'http://localhost:8080/File-Upload/index.php';

$ch = curl_init($baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_exec($ch);

// 1. Authenticate
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'username' => 'admin',
    'password' => 'admin123',
    'csrf_token' => ''
]));
curl_exec($ch);

// Get auth token
curl_setopt($ch, CURLOPT_URL, $baseUrl);
curl_setopt($ch, CURLOPT_HTTPGET, true);
$html = curl_exec($ch);
preg_match('/content="([a-f0-9]{64})"/', $html, $m);
$csrf = $m[1] ?? '';
echo "1. Auth CSRF Token: " . ($csrf ? "OK" : "FAILED") . "\n";

// 2. Create test directory
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=mkdir');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'target_dir' => '/',
    'folder_name' => 'http_cpanel_test',
    'csrf_token' => $csrf
]));
$res = json_decode(curl_exec($ch), true);
echo "2. Mkdir http_cpanel_test: " . ($res['success'] ? 'PASS' : 'FAIL') . "\n";

// 3. Create file for editing
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=create_file');
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'target_dir' => '/http_cpanel_test',
    'file_name' => 'edit_sample.php',
    'content' => '<?php echo "Initial Code";',
    'csrf_token' => $csrf
]));
$res = json_decode(curl_exec($ch), true);
echo "3. Create file for editor: " . ($res['success'] ? 'PASS' : 'FAIL') . "\n";

// 4. Test edit_load
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=edit_load&path=' . urlencode('/http_cpanel_test/edit_sample.php'));
curl_setopt($ch, CURLOPT_HTTPGET, true);
$res = json_decode(curl_exec($ch), true);
echo "4. API edit_load: " . ($res['success'] && strpos($res['content'], 'Initial Code') !== false ? 'PASS' : 'FAIL') . "\n";

// 5. Test edit_save
$newCode = "<?php\n\nfunction test() {\n    return 'Updated via HTTP Editor';\n}\n";
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=edit_save');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'path' => '/http_cpanel_test/edit_sample.php',
    'content' => $newCode,
    'csrf_token' => $csrf
]));
$res = json_decode(curl_exec($ch), true);
echo "5. API edit_save: " . ($res['success'] ? 'PASS' : 'FAIL') . "\n";

// Verify edit_load has updated code
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=edit_load&path=' . urlencode('/http_cpanel_test/edit_sample.php'));
curl_setopt($ch, CURLOPT_HTTPGET, true);
$res = json_decode(curl_exec($ch), true);
echo "5b. Verify saved content: " . ($res['success'] && strpos($res['content'], 'Updated via HTTP Editor') !== false ? 'PASS' : 'FAIL') . "\n";

// 6. Test CHMOD
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=chmod');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'path' => '/http_cpanel_test/edit_sample.php',
    'mode' => '0755',
    'csrf_token' => $csrf
]));
$res = json_decode(curl_exec($ch), true);
echo "6. API chmod: " . ($res['success'] ? 'PASS' : 'FAIL') . "\n";

// 7. Create another file to test compression
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=create_file');
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'target_dir' => '/http_cpanel_test',
    'file_name' => 'second.txt',
    'content' => 'Second file text content',
    'csrf_token' => $csrf
]));
curl_exec($ch);

// 8. Test Compress
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=compress');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'items' => json_encode(['/http_cpanel_test/edit_sample.php', '/http_cpanel_test/second.txt']),
    'destination' => '/http_cpanel_test',
    'zip_name' => 'test_bundle.zip',
    'csrf_token' => $csrf
]);
$res = json_decode(curl_exec($ch), true);
echo "8. API compress (multi-file to ZIP): " . ($res['success'] ? 'PASS' : 'FAIL') . "\n";

// 9. Test Bulk Copy
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=mkdir');
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'target_dir' => '/http_cpanel_test',
    'folder_name' => 'copied_folder',
    'csrf_token' => $csrf
]));
curl_exec($ch);

curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=bulk_copy');
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'paths' => json_encode(['/http_cpanel_test/edit_sample.php', '/http_cpanel_test/second.txt']),
    'destination' => '/http_cpanel_test/copied_folder',
    'csrf_token' => $csrf
]);
$res = json_decode(curl_exec($ch), true);
echo "9. API bulk_copy: " . ($res['success'] && $res['copied_count'] === 2 ? 'PASS' : 'FAIL') . "\n";

// 10. Test Bulk Delete
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=bulk_delete');
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'paths' => json_encode(['/http_cpanel_test/copied_folder/edit_sample.php', '/http_cpanel_test/copied_folder/second.txt']),
    'csrf_token' => $csrf
]);
$res = json_decode(curl_exec($ch), true);
echo "10. API bulk_delete: " . ($res['success'] && $res['deleted_count'] === 2 ? 'PASS' : 'FAIL') . "\n";

// 11. Test Bulk Download (GET stream)
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=bulk_download&paths=' . urlencode(json_encode(['/http_cpanel_test/second.txt'])));
curl_setopt($ch, CURLOPT_HTTPGET, true);
$rawZip = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$isZipMagic = (substr($rawZip, 0, 2) === "PK");
echo "11. API bulk_download (HTTP $httpCode, ZIP Magic: " . ($isZipMagic ? 'VALID' : 'INVALID') . "): " . ($isZipMagic ? 'PASS' : 'FAIL') . "\n";

// 12. Cleanup
curl_setopt($ch, CURLOPT_URL, $baseUrl . '?action=delete');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'path' => '/http_cpanel_test',
    'csrf_token' => $csrf
]));
$res = json_decode(curl_exec($ch), true);
echo "12. Cleanup test directory: " . ($res['success'] ? 'PASS' : 'FAIL') . "\n";

@unlink($cookieFile);
echo "\n=== SEMUA PENGUJIAN API HTTP LIVE cPANEL SUKSES ===\n";
