<?php
/**
 * Test Suite: Find in Files & Image Thumbnails (SaaS Premium Features)
 */

declare(strict_types=1);

define('APP_INIT', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/FileManager.php';

echo "=== START TEST SUITE: FIND IN FILES & THUMBNAILS ===\n\n";

$sandboxDir = ALLOWED_ROOT . '/sandbox/test_find_scope';
if (!is_dir($sandboxDir)) {
    mkdir($sandboxDir, 0777, true);
}

// Buat beberapa file dummy untuk pencarian
$file1 = $sandboxDir . '/welcome.php';
file_put_contents($file1, "<?php\necho 'Selamat datang di Aplikasi File Manager';\n\$appSecret = 'SecretToken123';\n");

$subDir = $sandboxDir . '/subdir';
if (!is_dir($subDir)) {
    mkdir($subDir, 0777, true);
}

$file2 = $subDir . '/helpers.js';
file_put_contents($file2, "// Helper script\nfunction calculateTotal() {\n    return 42;\n}\nconst secretToken = 'SecretToken123';\n");

$file3 = $subDir . '/README.txt';
file_put_contents($file3, "Dokumentasi proyek.\nToken rahasia tidak boleh bocor: secrettoken123.\nSelesai.\n");

$testPassed = 0;
$testTotal = 0;

function assertTest(bool $condition, string $testName): void {
    global $testPassed, $testTotal;
    $testTotal++;
    if ($condition) {
        $testPassed++;
        echo "  [PASS] {$testName}\n";
    } else {
        echo "  [FAIL] {$testName}\n";
    }
}

// 1. Basic String Search across directory and subdirectories
echo "1. Pengujian Pencarian String Standar (Case Insensitive, Recursive):\n";
$res1 = FileManager::findInFiles('/sandbox/test_find_scope', 'SecretToken123', true, false, false);
assertTest($res1['success'] === true, 'Pencarian berhasil dieksekusi');
assertTest($res1['total_matches'] === 3, "Ditemukan 3 kecocokan (didapat: {$res1['total_matches']})");
assertTest($res1['scanned_files'] >= 3, "File terpindai >= 3");

// 2. Case Sensitive Search
echo "\n2. Pengujian Pencarian Case Sensitive:\n";
$res2 = FileManager::findInFiles('/sandbox/test_find_scope', 'SecretToken123', true, true, false);
assertTest($res2['success'] === true, 'Pencarian case-sensitive berhasil');
assertTest($res2['total_matches'] === 2, "Ditemukan 2 kecocokan persis besar/kecil (didapat: {$res2['total_matches']})");

// 3. Non-recursive search (hanya file di direktori aktif)
echo "\n3. Pengujian Pencarian Non-Rekursif (Single Directory):\n";
$res3 = FileManager::findInFiles('/sandbox/test_find_scope', 'SecretToken123', false, false, false);
assertTest($res3['success'] === true, 'Pencarian non-rekursif berhasil');
assertTest($res3['total_matches'] === 1, "Hanya menemukan 1 match di folder aktif tanpa subdir (didapat: {$res3['total_matches']})");

// 4. Regex Pattern Search
echo "\n4. Pengujian Pencarian Menggunakan Regular Expression (Regex):\n";
$res4 = FileManager::findInFiles('/sandbox/test_find_scope', 'function\s+\w+\(\)', true, false, true);
assertTest($res4['success'] === true, 'Pencarian regex berhasil');
assertTest($res4['total_matches'] === 1, "Ditemukan 1 kecocokan deklarasi function di helpers.js (didapat: {$res4['total_matches']})");

// 5. Invalid Regex handling
echo "\n5. Pengujian Penanganan Regex yang Tidak Valid (Syntax Error):\n";
$res5 = FileManager::findInFiles('/sandbox/test_find_scope', '([a-z', true, false, true);
assertTest($res5['success'] === false, 'Regex tidak valid ditolak secara anggun');
assertTest(strpos($res5['message'], 'tidak valid') !== false, 'Pesan error regex informatif');

// 6. Empty query handling
echo "\n6. Pengujian Kata Kunci Kosong:\n";
$res6 = FileManager::findInFiles('/sandbox/test_find_scope', '   ', true, false, false);
assertTest($res6['success'] === false, 'Query kosong ditolak');

// 7. Test listDirectory 'is_image' property
echo "\n7. Pengujian Property 'is_image' pada FileManager::listDirectory:\n";
$listRes = FileManager::listDirectory('/screenshots');
assertTest($listRes['success'] === true, 'listDirectory screenshots berhasil');
$hasImages = false;
foreach ($listRes['items'] as $item) {
    if (in_array(strtolower($item['extension']), ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'])) {
        if (!empty($item['is_image'])) {
            $hasImages = true;
            break;
        }
    }
}
assertTest($hasImages === true, 'Berkas gambar di-flag is_image: true untuk thumbnail rendering');

// Cleanup dummy sandbox
@unlink($file1);
@unlink($file2);
@unlink($file3);
@rmdir($subDir);
@rmdir($sandboxDir);

echo "\n=== RINGKASAN HASIL TEST ===\n";
echo "Total Uji: {$testTotal}, Lolos: {$testPassed}, Gagal: " . ($testTotal - $testPassed) . "\n";

if ($testPassed === $testTotal) {
    echo "SEMUA UJI BERHASIL (100% PASS)!\n";
    exit(0);
} else {
    echo "ADA UJI YANG GAGAL!\n";
    exit(1);
}
