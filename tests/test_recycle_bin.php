<?php
define('APP_INIT', true);
define('STORAGE_PATH', __DIR__ . '/../storage');
define('LOG_FILE', __DIR__ . '/../storage/logs/test_trash_audit.log');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/Logger.php';
require_once __DIR__ . '/../app/FileManager.php';
$config = json_decode(file_get_contents(__DIR__ . '/../storage/credentials.json'), true);
$root = $config['allowed_root'] ?? 'C:\\xampp\\htdocs';
define('ALLOWED_ROOT', $root);
$passed = 0; $failed = 0; $errors = [];
function assert_ok(bool $cond, string $msg): void { global $passed,$failed,$errors; if($cond){echo "  PASS: $msg\n";$passed++;}else{echo "  FAIL: $msg\n";$failed++;$errors[]=$msg;} }
$testDir = $root . DIRECTORY_SEPARATOR . '__test_trash_' . time();
mkdir($testDir, 0777, true);
$testFile = $testDir . DIRECTORY_SEPARATOR . 'sample.txt';
file_put_contents($testFile, 'Hello Trash!');
$testSub = $testDir . DIRECTORY_SEPARATOR . 'sub_folder';
mkdir($testSub, 0777, true);
file_put_contents($testSub . DIRECTORY_SEPARATOR . 'inner.txt', 'inner');
$vFile = Security::toVirtualPath($testFile);
$vSub  = Security::toVirtualPath($testSub);
echo "\n=== Recycle Bin Test Suite ===\n\n";
echo "--- TEST 1: trashItem (file) ---\n";
$r = FileManager::trashItem($vFile);
assert_ok($r['success'], 'trashItem file success');
assert_ok(!empty($r['trash_id']), 'trashItem returns trash_id');
assert_ok(!file_exists($testFile), 'file removed from original location');
$tid1 = $r['trash_id'] ?? null;
echo "\n--- TEST 2: listTrash ---\n";
$r = FileManager::listTrash();
assert_ok($r['success'], 'listTrash success');
assert_ok(is_array($r['items']), 'listTrash items is array');
assert_ok($r['count'] >= 1, 'listTrash count >= 1');
$found = false;
foreach($r['items'] as $it){ if($it['trash_id']===$tid1){$found=true;assert_ok($it['name']==='sample.txt','correct filename in listTrash');assert_ok(!$it['is_dir'],'is_dir false for file');break;} }
assert_ok($found,'trashed file found in listTrash');
echo "\n--- TEST 3: restoreFromTrash ---\n";
$r = FileManager::restoreFromTrash($tid1);
assert_ok($r['success'], 'restoreFromTrash success');
$restored = file_exists($testFile) || glob($testDir.DIRECTORY_SEPARATOR.'sample*.txt');
assert_ok((bool)$restored, 'file exists after restore');
if(file_exists($testFile)) FileManager::trashItem($vFile);
echo "\n--- TEST 4: trashItem (folder) ---\n";
$r = FileManager::trashItem($vSub);
assert_ok($r['success'], 'trashItem folder success');
assert_ok(!is_dir($testSub), 'folder removed from original');
$tid2 = $r['trash_id'] ?? null;
echo "\n--- TEST 5: deletePermanent ---\n";
if($tid2){
  $r = FileManager::deletePermanent($tid2);
  assert_ok($r['success'], 'deletePermanent success');
  $list = FileManager::listTrash();
  $still = false;
  foreach($list['items'] as $it){if($it['trash_id']===$tid2)$still=true;}
  assert_ok(!$still, 'permanently deleted item not in trash');
}
echo "\n--- TEST 6: bulkTrash ---\n";
$f1=$testDir.DIRECTORY_SEPARATOR.'b1.txt'; file_put_contents($f1,'b1');
$f2=$testDir.DIRECTORY_SEPARATOR.'b2.txt'; file_put_contents($f2,'b2');
$r = FileManager::bulkTrash([Security::toVirtualPath($f1), Security::toVirtualPath($f2)]);
assert_ok($r['success'], 'bulkTrash success');
assert_ok($r['trashed_count']===2, 'bulkTrash trashed_count=2');
assert_ok(!file_exists($f1)&&!file_exists($f2), 'bulk files removed');
echo "\n--- TEST 7: emptyTrash ---\n";
$r = FileManager::emptyTrash();
assert_ok($r['success'], 'emptyTrash success');
$list = FileManager::listTrash();
assert_ok($list['count']===0, 'listTrash count=0 after emptyTrash');
echo "\n--- TEST 8: Protected paths ---\n";
assert_ok(!FileManager::trashItem('/storage/credentials.json')['success'], 'cannot trash credentials.json');
assert_ok(!FileManager::trashItem('/')['success'], 'cannot trash root');
// Cleanup
$it2=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testDir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($it2 as $f){if($f->isDir())@rmdir($f->getPathname());else@unlink($f->getPathname());}
@rmdir($testDir);
@unlink(LOG_FILE);
echo "\n=== RESULTS: {$passed} passed, {$failed} failed ===\n";
if(!empty($errors)){foreach($errors as $e)echo"  - $e\n";}
exit($failed>0?1:0);
