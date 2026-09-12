<?php
/**
 * Automated Verification for 4 New Features:
 * 1. Disk Usage / Quota Meter
 * 2. 1-Click Duplicate Item
 * 3. Syntax Highlighting in Code Editor
 * 4. Dark / Light Mode Theme System
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/Logger.php';
require_once __DIR__ . '/../app/FileManager.php';

$testDir = __DIR__ . '/../sandbox/test_new_feat_' . time();
if (!is_dir($testDir)) {
    mkdir($testDir, 0777, true);
}

$passed = 0;
$failed = 0;

function assertCondition($cond, $label) {
    global $passed, $failed;
    if ($cond) {
        echo "[PASS] $label\n";
        $passed++;
    } else {
        echo "[FAIL] $label\n";
        $failed++;
    }
}

echo "=== Testing Feature 1: Disk Usage ===\n";
$disk = FileManager::getDiskUsage();
assertCondition(isset($disk['available']), "Disk usage has 'available' key");
if ($disk['available']) {
    assertCondition(isset($disk['total_bytes']) && $disk['total_bytes'] > 0, "total_bytes > 0");
    assertCondition(isset($disk['free_bytes']) && $disk['free_bytes'] > 0, "free_bytes > 0");
    assertCondition(isset($disk['used_bytes']), "used_bytes exists");
    assertCondition(isset($disk['percentage']) && $disk['percentage'] >= 0 && $disk['percentage'] <= 100, "percentage between 0 and 100 ({$disk['percentage']}%)");
    assertCondition(!empty($disk['total_human']), "total_human exists ({$disk['total_human']})");
    assertCondition(!empty($disk['free_human']), "free_human exists ({$disk['free_human']})");
    assertCondition(!empty($disk['used_human']), "used_human exists ({$disk['used_human']})");
}

echo "\n=== Testing Feature 2: 1-Click Duplicate ===\n";
$sampleFilePath = $testDir . '/hello_world.txt';
file_put_contents($sampleFilePath, "Hello world test content");

$vPath = Security::toVirtualPath($sampleFilePath);
$res1 = FileManager::duplicateItem($vPath);
assertCondition($res1['success'] === true, "File duplication succeeded: " . ($res1['message'] ?? ''));
assertCondition($res1['copy_name'] === 'hello_world_copy.txt', "First duplicate name is 'hello_world_copy.txt'");
assertCondition(file_exists($testDir . '/hello_world_copy.txt'), "Duplicate file exists on filesystem");
assertCondition(file_get_contents($testDir . '/hello_world_copy.txt') === "Hello world test content", "Duplicate file content matches original");

// Duplicate again to verify auto-increment suffix (_copy2)
$res2 = FileManager::duplicateItem($vPath);
assertCondition($res2['success'] === true, "Second duplication succeeded");
assertCondition($res2['copy_name'] === 'hello_world_copy2.txt', "Second duplicate name is 'hello_world_copy2.txt'");
assertCondition(file_exists($testDir . '/hello_world_copy2.txt'), "hello_world_copy2.txt exists on filesystem");

// Test directory duplication
$sampleSubdir = $testDir . '/sample_dir';
mkdir($sampleSubdir, 0777, true);
file_put_contents($sampleSubdir . '/inner.php', "<?php echo 'inner';");

$vDirPath = Security::toVirtualPath($sampleSubdir);
$resDir = FileManager::duplicateItem($vDirPath);
assertCondition($resDir['success'] === true, "Directory duplication succeeded");
assertCondition($resDir['copy_name'] === 'sample_dir_copy', "Duplicate dir name is 'sample_dir_copy'");
assertCondition(is_dir($testDir . '/sample_dir_copy'), "sample_dir_copy directory exists");
assertCondition(file_exists($testDir . '/sample_dir_copy/inner.php'), "Recursive inner file copied correctly");

echo "\n=== Testing Feature 3 & 4: CSS and Frontend Assets ===\n";
$cssContent = file_get_contents(__DIR__ . '/../assets/css/style.css');
assertCondition(strpos($cssContent, '[data-theme="dark"]') !== false, "Dark theme rules found in style.css");
assertCondition(strpos($cssContent, '.disk-meter') !== false, "Disk meter styling found in style.css");
assertCondition(strpos($cssContent, '.btn-theme-toggle') !== false, "Theme toggle button styling found in style.css");
assertCondition(strpos($cssContent, '.code-editor-pre') !== false, "Overlay pre styling found in style.css");
assertCondition(strpos($cssContent, '.tok-kw') !== false, "Syntax token styles found in style.css");

$indexContent = file_get_contents(__DIR__ . '/../index.php');
assertCondition(strpos($indexContent, 'id="diskMeter"') !== false, "diskMeter element present in index.php");
assertCondition(strpos($indexContent, 'id="btnThemeToggle"') !== false, "btnThemeToggle element present in index.php");
assertCondition(strpos($indexContent, 'id="btnDuplicateSelected"') !== false, "btnDuplicateSelected element present in index.php");
assertCondition(strpos($indexContent, 'id="ctxDuplicate"') !== false, "ctxDuplicate element present in index.php");
assertCondition(strpos($indexContent, 'id="editorLangSelect"') !== false, "editorLangSelect element present in index.php");
assertCondition(strpos($indexContent, 'id="btnEditorHighlight"') !== false, "btnEditorHighlight element present in index.php");

$jsContent = file_get_contents(__DIR__ . '/../assets/js/app.js');
assertCondition(strpos($jsContent, 'function initTheme') !== false, "initTheme function found in app.js");
assertCondition(strpos($jsContent, 'function toggleTheme') !== false, "toggleTheme function found in app.js");
assertCondition(strpos($jsContent, 'function updateDiskMeter') !== false, "updateDiskMeter function found in app.js");
assertCondition(strpos($jsContent, 'function duplicateSingleItem') !== false, "duplicateSingleItem function found in app.js");
assertCondition(strpos($jsContent, 'function highlightSyntax') !== false, "highlightSyntax function found in app.js");
assertCondition(strpos($jsContent, 'function detectLanguage') !== false, "detectLanguage function found in app.js");

// Cleanup test sandbox
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . "/" . $object)) rrmdir($dir . "/" . $object);
                else unlink($dir . "/" . $object);
            }
        }
        rmdir($dir);
    }
}
rrmdir($testDir);

echo "\n============================================\n";
echo "SUMMARY: Passed: $passed, Failed: $failed\n";
if ($failed === 0) {
    echo "ALL NEW FEATURES VERIFIED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
