<?php
if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access denied.');
}

/**
 * Core File Manager Engine
 * Menangani operasi direktori, file, streaming download, dan preview.
 */

require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Logger.php';

class FileManager
{
    /**
     * Membaca isi direktori dan menghasilkan listing yang siap ditampilkan di tabel.
     */
    public static function listDirectory(string $relativePath = '/', $sortField = 'name', string $sortOrder = 'asc', bool $showHidden = false): array
    {
        if (is_bool($sortField)) {
            $showHidden = $sortField;
            $sortField = 'name';
        }

        $realPath = Security::resolvePath($relativePath, true);
        if ($realPath === null || !is_dir($realPath)) {
            return [
                'success' => false,
                'message' => 'Direktori tidak ditemukan atau akses ditolak.'
            ];
        }

        $items = [];
        $files = @scandir($realPath);
        if ($files === false) {
            return [
                'success' => false,
                'message' => 'Gagal membaca isi direktori.'
            ];
        }

        $virtualCurrent = Security::toVirtualPath($realPath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $isHidden = str_starts_with($file, '.');
            if (!$showHidden && $isHidden) {
                continue;
            }

            $itemFullPath = $realPath . DIRECTORY_SEPARATOR . $file;
            $itemVirtualPath = rtrim($virtualCurrent, '/') . '/' . $file;
            $isDir = is_dir($itemFullPath);
            $isProtected = Security::isProtected($itemVirtualPath);

            $stat = @stat($itemFullPath);
            $size = $stat ? $stat['size'] : 0;
            $mtime = $stat ? $stat['mtime'] : 0;
            $perms = $stat ? $stat['mode'] : 0;

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $isZip = !$isDir && ($ext === 'zip');
            $isText = !$isDir && Security::isTextPreviewable($file);

            // Klasifikasi tipe file
            if ($isDir) {
                $type = 'Folder';
            } elseif ($isZip) {
                $type = 'ZIP Archive';
            } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'])) {
                $type = 'Image (' . strtoupper($ext) . ')';
            } elseif (in_array($ext, ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8'])) {
                $type = 'PHP Script';
            } elseif (in_array($ext, ['html', 'htm'])) {
                $type = 'HTML Document';
            } elseif ($ext === 'css') {
                $type = 'CSS Stylesheet';
            } elseif ($ext === 'js') {
                $type = 'JavaScript File';
            } elseif ($ext === 'json') {
                $type = 'JSON File';
            } elseif ($ext === 'sql') {
                $type = 'SQL Database Script';
            } elseif ($ext === 'txt' || $ext === 'md') {
                $type = 'Text Document';
            } else {
                $type = $ext !== '' ? strtoupper($ext) . ' File' : 'File';
            }

            $permData = Security::formatPermissions($perms);

            $items[] = [
                'name'         => $file,
                'virtual_path' => $itemVirtualPath,
                'is_dir'       => $isDir,
                'is_zip'       => $isZip,
                'is_text'      => $isText,
                'is_hidden'    => $isHidden,
                'is_editable'  => !$isDir && $isText,
                'is_protected' => $isProtected,
                'type'         => $type,
                'extension'    => $ext,
                'size_raw'     => $isDir ? 0 : $size,
                'size_human'   => $isDir ? '-' : Security::formatBytes($size),
                'mtime_raw'    => $mtime,
                'mtime_human'  => $mtime > 0 ? date('d M Y H:i', $mtime) : '-',
                'perms_octal'  => $permData['octal'],
                'perms_rwx'    => $permData['rwx'],
            ];
        }

        // Sorting: Folder selalu di atas atau sesuai field
        usort($items, function ($a, $b) use ($sortField, $sortOrder) {
            // Jika sortField bukan khusus, kelompokkan folder di atas
            if ($a['is_dir'] !== $b['is_dir']) {
                return $a['is_dir'] ? -1 : 1;
            }

            $multiplier = ($sortOrder === 'desc') ? -1 : 1;

            switch ($sortField) {
                case 'size':
                    return ($a['size_raw'] <=> $b['size_raw']) * $multiplier;
                case 'modified':
                    return ($a['mtime_raw'] <=> $b['mtime_raw']) * $multiplier;
                case 'type':
                    return strcasecmp($a['type'], $b['type']) * $multiplier;
                case 'name':
                default:
                    return strcasecmp($a['name'], $b['name']) * $multiplier;
            }
        });

        return [
            'success'      => true,
            'current_path' => $virtualCurrent,
            'breadcrumbs'  => self::buildBreadcrumbs($virtualCurrent),
            'items'        => $items,
            'count'        => count($items),
            'show_hidden'  => $showHidden
        ];
    }

    /**
     * Membangun struktur breadcrumbs untuk navigasi.
     */
    public static function buildBreadcrumbs(string $virtualPath): array
    {
        $virtualPath = trim($virtualPath, '/');
        $crumbs = [
            ['name' => 'Root', 'path' => '/']
        ];

        if ($virtualPath === '') {
            return $crumbs;
        }

        $parts = explode('/', $virtualPath);
        $accum = '';

        foreach ($parts as $part) {
            if ($part === '') continue;
            $accum .= '/' . $part;
            $crumbs[] = [
                'name' => $part,
                'path' => $accum
            ];
        }

        return $crumbs;
    }

    /**
     * Membuat folder baru di direktori target.
     */
    public static function createFolder(string $currentPath, string $folderName): array
    {
        $folderName = trim($folderName);
        if (!Security::isValidFilename($folderName)) {
            return ['success' => false, 'message' => 'Nama folder tidak valid atau mengandung karakter terlarang.'];
        }

        $parentPath = Security::resolvePath($currentPath, true);
        if ($parentPath === null || !is_dir($parentPath)) {
            return ['success' => false, 'message' => 'Direktori tujuan tidak valid.'];
        }

        $newDir = $parentPath . DIRECTORY_SEPARATOR . $folderName;
        if (file_exists($newDir)) {
            return ['success' => false, 'message' => 'Folder atau file dengan nama tersebut sudah ada.'];
        }

        if (!@mkdir($newDir, 0755)) {
            return ['success' => false, 'message' => 'Gagal membuat folder. Periksa permission server.'];
        }

        Logger::log('NEW_FOLDER', Security::toVirtualPath($newDir), 'SUCCESS');
        return ['success' => true, 'message' => "Folder '$folderName' berhasil dibuat."];
    }

    /**
     * Membuat berkas (file) baru di direktori target (sisipkan file baru).
     */
    public static function createFile(string $currentPath, string $fileName, string $content = ''): array
    {
        $fileName = trim($fileName);
        if (!Security::isValidFilename($fileName)) {
            return ['success' => false, 'message' => 'Nama berkas tidak valid atau mengandung karakter terlarang.'];
        }

        $parentPath = Security::resolvePath($currentPath, true);
        if ($parentPath === null || !is_dir($parentPath)) {
            return ['success' => false, 'message' => 'Direktori tujuan tidak valid.'];
        }

        $newFile = $parentPath . DIRECTORY_SEPARATOR . $fileName;
        $virtualTarget = Security::toVirtualPath($newFile);

        if (Security::isProtected($virtualTarget)) {
            return ['success' => false, 'message' => 'Nama berkas ini termasuk dalam daftar berkas terproteksi.'];
        }

        if (file_exists($newFile)) {
            return ['success' => false, 'message' => 'Berkas dengan nama tersebut sudah ada.'];
        }

        if (@file_put_contents($newFile, $content) === false) {
            return ['success' => false, 'message' => 'Gagal membuat berkas. Periksa permission server.'];
        }

        Logger::log('NEW_FILE', $virtualTarget, 'SUCCESS');
        return ['success' => true, 'message' => "Berkas '$fileName' berhasil dibuat."];
    }

    /**
     * Menyimpan file yang diunggah ke direktori target yang aman.
     * Mendukung validasi ukuran, ekstensi, penolakan file terproteksi, sanitasi nama,
     * serta mendukung move_uploaded_file untuk HTTP upload dan fallback copy untuk CLI testing.
     */
    public static function saveUploadedFile(string $targetDir, string $sourcePath, string $originalName, int $fileSize, int $errorCode = UPLOAD_ERR_OK, bool $isHttpUpload = true): array
    {
        $realTargetDir = Security::resolvePath($targetDir, true);
        if ($realTargetDir === null || !is_dir($realTargetDir)) {
            return [
                'success' => false,
                'message' => 'Direktori tujuan upload tidak valid atau akses ditolak.'
            ];
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            switch ($errorCode) {
                case UPLOAD_ERR_INI_SIZE:
                    $errMessage = 'Ukuran file melebihi batas upload_max_filesize server (' . ini_get('upload_max_filesize') . ').';
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $errMessage = 'Ukuran file melebihi batas MAX_FILE_SIZE formulir.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errMessage = 'File hanya terunggah sebagian (koneksi jaringan terputus).';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errMessage = 'Tidak ada file yang diunggah.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errMessage = 'Folder temporary server (upload_tmp_dir) tidak ditemukan.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errMessage = 'Gagal menulis file ke disk server (disk penuh atau permission ditolak).';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errMessage = 'Upload dihentikan oleh ekstensi PHP server.';
                    break;
                default:
                    $errMessage = 'Error upload code: ' . $errorCode;
                    break;
            }
            return [
                'success' => false,
                'message' => $errMessage,
                'error_code' => $errorCode
            ];
        }

        $maxSize = defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 200 * 1024 * 1024;
        if ($maxSize > 0 && $fileSize > $maxSize) {
            return [
                'success' => false,
                'message' => 'Ukuran file melebihi batas maksimal aplikasi (' . Security::formatBytes($maxSize) . ').'
            ];
        }

        $sanitizedName = Security::sanitizeFilename($originalName);
        if (empty($sanitizedName)) {
            return [
                'success' => false,
                'message' => 'Nama file tidak valid setelah sanitasi.'
            ];
        }

        $destination = $realTargetDir . DIRECTORY_SEPARATOR . $sanitizedName;
        $virtualTarget = Security::toVirtualPath($destination);

        if (Security::isProtected($virtualTarget)) {
            return [
                'success' => false,
                'message' => 'Dilarang menimpa file terproteksi sistem.'
            ];
        }

        $success = false;
        if ($isHttpUpload && is_uploaded_file($sourcePath)) {
            $success = @move_uploaded_file($sourcePath, $destination);
        } else {
            // Fallback untuk pengujian CLI atau environment lokal
            $success = @copy($sourcePath, $destination);
            if ($success && $isHttpUpload && file_exists($sourcePath)) {
                @unlink($sourcePath);
            }
        }

        if ($success && file_exists($destination)) {
            $actualSize = filesize($destination);
            Logger::log('UPLOAD', $virtualTarget, 'SUCCESS', Security::formatBytes($actualSize));
            return [
                'success' => true,
                'message' => "File '$sanitizedName' berhasil diunggah.",
                'name' => $sanitizedName,
                'virtual_path' => $virtualTarget,
                'size' => $actualSize,
                'formatted_size' => Security::formatBytes($actualSize)
            ];
        } else {
            Logger::log('UPLOAD', $virtualTarget, 'FAILED', 'Permission error, move_uploaded_file failure or file missing after move');
            return [
                'success' => false,
                'message' => "Gagal memindahkan file '$sanitizedName' ke folder tujuan. Periksa permission folder atau sisa ruang disk."
            ];
        }
    }

    /**
     * Mengubah nama (rename) file atau folder.
     */
    public static function renameItem(string $relativePath, string $newName): array
    {
        $newName = trim($newName);
        if (!Security::isValidFilename($newName)) {
            return ['success' => false, 'message' => 'Nama baru tidak valid.'];
        }

        if (Security::isProtected($relativePath)) {
            return ['success' => false, 'message' => 'Item ini dilindungi dan tidak dapat diubah namanya.'];
        }

        $sourcePath = Security::resolvePath($relativePath, true);
        if ($sourcePath === null) {
            return ['success' => false, 'message' => 'Item tidak ditemukan.'];
        }

        $parentDir = dirname($sourcePath);
        $destinationPath = $parentDir . DIRECTORY_SEPARATOR . $newName;

        if (file_exists($destinationPath)) {
            return ['success' => false, 'message' => 'File atau folder dengan nama tersebut sudah ada.'];
        }

        // Jangan izinkan me-rename menjadi nama protected file
        if (Security::isProtected(Security::toVirtualPath($destinationPath))) {
            return ['success' => false, 'message' => 'Nama target termasuk dalam daftar file terproteksi.'];
        }

        if (!@rename($sourcePath, $destinationPath)) {
            return ['success' => false, 'message' => 'Gagal mengubah nama item.'];
        }

        Logger::log('RENAME', Security::toVirtualPath($sourcePath) . " -> $newName", 'SUCCESS');
        return ['success' => true, 'message' => 'Nama berhasil diubah.'];
    }

    /**
     * Menghapus file atau folder secara aman.
     */
    public static function deleteItem(string $relativePath): array
    {
        if (Security::isProtected($relativePath)) {
            return ['success' => false, 'message' => 'Item ini dilindungi dan tidak dapat dihapus.'];
        }

        $realPath = Security::resolvePath($relativePath, true);
        if ($realPath === null) {
            return ['success' => false, 'message' => 'Item tidak ditemukan atau akses ditolak.'];
        }

        // Cegah penghapusan ALLOWED_ROOT itu sendiri
        if (strcasecmp($realPath, Security::getRoot()) === 0) {
            return ['success' => false, 'message' => 'Tidak dapat menghapus root directory.'];
        }

        $virtualPath = Security::toVirtualPath($realPath);

        if (is_dir($realPath)) {
            $success = self::deleteDirectoryRecursive($realPath);
            if (!$success) {
                Logger::log('DELETE', $virtualPath, 'FAILED', 'Gagal menghapus beberapa isi folder');
                return ['success' => false, 'message' => 'Gagal menghapus folder. Beberapa file mungkin terproteksi atau terkunci.'];
            }
        } else {
            if (!@unlink($realPath)) {
                Logger::log('DELETE', $virtualPath, 'FAILED', 'Permission denied');
                return ['success' => false, 'message' => 'Gagal menghapus file. Periksa permission.'];
            }
        }

        Logger::log('DELETE', $virtualPath, 'SUCCESS');
        return ['success' => true, 'message' => 'Item berhasil dihapus.'];
    }

    /**
     * Rekursif menghapus folder dan seluruh isinya.
     */
    private static function deleteDirectoryRecursive(string $dir): bool
    {
        $items = @scandir($dir);
        if ($items === false) return false;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_link($path)) {
                if (!@unlink($path)) return false;
            } elseif (is_dir($path)) {
                if (!self::deleteDirectoryRecursive($path)) return false;
            } else {
                if (!@unlink($path)) return false;
            }
        }

        return @rmdir($dir);
    }

    /**
     * Menghitung isi folder (jumlah file & folder) dan total ukuran untuk dialog konfirmasi.
     */
    public static function getFolderStats(string $relativePath): array
    {
        $realPath = Security::resolvePath($relativePath, true);
        if ($realPath === null || !is_dir($realPath)) {
            return ['count' => 0, 'size' => 0, 'size_human' => '0 B'];
        }

        $fileCount = 0;
        $folderCount = 0;
        $totalSize = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $folderCount++;
            } else {
                $fileCount++;
                $totalSize += $item->getSize();
            }
        }

        return [
            'file_count'   => $fileCount,
            'folder_count' => $folderCount,
            'total_items'  => $fileCount + $folderCount,
            'total_size'   => $totalSize,
            'size_human'   => Security::formatBytes($totalSize)
        ];
    }

    /**
     * Mendapatkan informasi detail file atau folder untuk modal Info.
     */
    public static function getItemInfo(string $relativePath): array
    {
        $realPath = Security::resolvePath($relativePath, true);
        if ($realPath === null) {
            return ['success' => false, 'message' => 'Item tidak ditemukan.'];
        }

        $stat = @stat($realPath);
        $isDir = is_dir($realPath);
        $name = basename($realPath);
        $virtual = Security::toVirtualPath($realPath);
        $permData = Security::formatPermissions($stat ? $stat['mode'] : 0);

        $isProtected = Security::isProtected($virtual);
        $isEditable = !$isDir && Security::isTextPreviewable($name);

        $data = [
            'success'      => true,
            'name'         => $name,
            'virtual_path' => $virtual,
            'is_dir'       => $isDir,
            'is_protected' => $isProtected,
            'is_editable'  => $isEditable,
            'created'      => $stat && !empty($stat['ctime']) ? date('d F Y H:i:s', $stat['ctime']) : '-',
            'modified'     => $stat && !empty($stat['mtime']) ? date('d F Y H:i:s', $stat['mtime']) : '-',
            'permissions'  => $permData['octal'] . ' (' . $permData['rwx'] . ')',
            'octal'        => $permData['octal'],
            'rwx'          => $permData['rwx'],
            'owner'        => function_exists('posix_getpwuid') && $stat ? (posix_getpwuid($stat['uid'])['name'] ?? $stat['uid']) : ($stat ? $stat['uid'] : '-'),
            'group'        => function_exists('posix_getgrgid') && $stat ? (posix_getgrgid($stat['gid'])['name'] ?? $stat['gid']) : ($stat ? $stat['gid'] : '-'),
        ];

        if ($isDir) {
            $stats = self::getFolderStats($relativePath);
            $data['type'] = 'Directory';
            $data['items_count'] = $stats['total_items'];
            $data['size_human'] = $stats['size_human'];
        } else {
            $size = $stat ? $stat['size'] : 0;
            $data['size_raw'] = $size;
            $data['size_human'] = Security::formatBytes($size);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $data['type'] = $ext !== '' ? strtoupper($ext) . ' File' : 'Regular File';

            // Deteksi MIME secara aman
            $mime = 'application/octet-stream';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $detected = finfo_file($finfo, $realPath);
                    if ($detected) $mime = $detected;
                    finfo_close($finfo);
                }
            } elseif (function_exists('mime_content_type')) {
                $mime = mime_content_type($realPath) ?: $mime;
            }
            $data['mime'] = $mime;
        }

        return $data;
    }

    /**
     * Membaca isi file teks untuk pratinjau (preview).
     * TIDAK MENGEKSEKUSI KODE APAPUN. Dibaca murni sebagai plain text.
     */
    public static function getFilePreview(string $relativePath): array
    {
        $realPath = Security::resolvePath($relativePath, true);
        if ($realPath === null || is_dir($realPath)) {
            return ['success' => false, 'message' => 'File tidak ditemukan.'];
        }

        $filename = basename($realPath);
        if (!Security::isTextPreviewable($filename)) {
            return ['success' => false, 'message' => 'Tipe file ini tidak didukung untuk pratinjau teks.'];
        }

        $size = filesize($realPath);
        $maxPreview = 2 * 1024 * 1024; // 2 MB limit
        if ($size > $maxPreview) {
            return ['success' => false, 'message' => 'File terlalu besar untuk pratinjau langsung (maks. 2 MB).'];
        }

        $content = @file_get_contents($realPath);
        if ($content === false) {
            return ['success' => false, 'message' => 'Gagal membaca isi file.'];
        }

        // Konversi encoding ke UTF-8 jika perlu
        if (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
            if (function_exists('mb_convert_encoding')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1, Windows-1252, ASCII');
            } elseif (function_exists('utf8_encode')) {
                $content = utf8_encode($content);
            }
        }

        return [
            'success'      => true,
            'name'         => $filename,
            'virtual_path' => Security::toVirtualPath($realPath),
            'size_human'   => Security::formatBytes($size),
            'extension'    => strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
            'content'      => $content
        ];
    }

    /**
     * Menyalin file atau folder (rekursif).
     */
    public static function copyItem(string $sourceRelative, string $destRelativeDir): array
    {
        $sourceReal = Security::resolvePath($sourceRelative, true);
        $destDirReal = Security::resolvePath($destRelativeDir, true);

        if ($sourceReal === null || $destDirReal === null || !is_dir($destDirReal)) {
            return ['success' => false, 'message' => 'Path sumber atau tujuan tidak valid.'];
        }

        $basename = basename($sourceReal);
        $targetReal = $destDirReal . DIRECTORY_SEPARATOR . $basename;

        // Cegah copy ke dalam diri sendiri
        if (is_dir($sourceReal) && Security::isInsideRoot($targetReal, $sourceReal)) {
            return ['success' => false, 'message' => 'Tidak dapat menyalin folder ke dalam dirinya sendiri.'];
        }

        if (file_exists($targetReal)) {
            return ['success' => false, 'message' => "Item '$basename' sudah ada di folder tujuan."];
        }

        if (is_dir($sourceReal)) {
            $success = self::copyDirectoryRecursive($sourceReal, $targetReal);
        } else {
            $success = @copy($sourceReal, $targetReal);
        }

        if (!$success) {
            Logger::log('COPY', Security::toVirtualPath($sourceReal) . " -> " . Security::toVirtualPath($targetReal), 'FAILED');
            return ['success' => false, 'message' => 'Gagal menyalin item.'];
        }

        Logger::log('COPY', Security::toVirtualPath($sourceReal) . " -> " . Security::toVirtualPath($targetReal), 'SUCCESS');
        return ['success' => true, 'message' => "Item '$basename' berhasil disalin."];
    }

    private static function copyDirectoryRecursive(string $src, string $dst): bool
    {
        if (!@mkdir($dst, 0755, true) && !is_dir($dst)) {
            return false;
        }

        $items = @scandir($src);
        if ($items === false) return false;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $srcPath = $src . DIRECTORY_SEPARATOR . $item;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $item;

            if (is_link($srcPath)) {
                // Jangan dereference symlink untuk mencegah arbitary file copy keluar dari root
                continue;
            } elseif (is_dir($srcPath)) {
                if (!self::copyDirectoryRecursive($srcPath, $dstPath)) return false;
            } else {
                if (!@copy($srcPath, $dstPath)) return false;
            }
        }
        return true;
    }

    /**
     * Memindahkan file atau folder.
     */
    public static function moveItem(string $sourceRelative, string $destRelativeDir): array
    {
        if (Security::isProtected($sourceRelative)) {
            return ['success' => false, 'message' => 'Item ini dilindungi dan tidak dapat dipindahkan.'];
        }

        $sourceReal = Security::resolvePath($sourceRelative, true);
        $destDirReal = Security::resolvePath($destRelativeDir, true);

        if ($sourceReal === null || $destDirReal === null || !is_dir($destDirReal)) {
            return ['success' => false, 'message' => 'Path sumber atau tujuan tidak valid.'];
        }

        // Cegah memindahkan root
        if (strcasecmp($sourceReal, Security::getRoot()) === 0) {
            return ['success' => false, 'message' => 'Tidak dapat memindahkan root directory.'];
        }

        $basename = basename($sourceReal);
        $targetReal = $destDirReal . DIRECTORY_SEPARATOR . $basename;

        // Cegah memindahkan folder ke dalam dirinya sendiri
        if (is_dir($sourceReal) && Security::isInsideRoot($targetReal, $sourceReal)) {
            return ['success' => false, 'message' => 'Tidak dapat memindahkan folder ke dalam dirinya sendiri.'];
        }

        if (file_exists($targetReal)) {
            return ['success' => false, 'message' => "Item '$basename' sudah ada di folder tujuan."];
        }

        if (!@rename($sourceReal, $targetReal)) {
            Logger::log('MOVE', Security::toVirtualPath($sourceReal) . " -> " . Security::toVirtualPath($targetReal), 'FAILED');
            return ['success' => false, 'message' => 'Gagal memindahkan item.'];
        }

        Logger::log('MOVE', Security::toVirtualPath($sourceReal) . " -> " . Security::toVirtualPath($targetReal), 'SUCCESS');
        return ['success' => true, 'message' => "Item '$basename' berhasil dipindahkan."];
    }

    /**
     * Menghasilkan struktur tree folder dalam ALLOWED_ROOT untuk modal destination selector.
     */
    public static function getFolderTree(): array
    {
        $root = Security::getRoot();
        return self::buildTreeRecursive($root, '/');
    }

    private static function buildTreeRecursive(string $dirPath, string $virtualParent): array
    {
        $nodes = [];
        $items = @scandir($dirPath);
        if ($items === false) return $nodes;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $fullPath = $dirPath . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $virtual = rtrim($virtualParent, '/') . '/' . $item;
                $children = self::buildTreeRecursive($fullPath, $virtual);
                $nodes[] = [
                    'name'     => $item,
                    'path'     => $virtual,
                    'children' => $children
                ];
            }
        }

        return $nodes;
    }

    /**
     * Mengalirkan file untuk didownload browser secara efisien (chunked).
     */
    public static function downloadFile(string $relativePath): void
    {
        $realPath = Security::resolvePath($relativePath, true);
        if ($realPath === null || is_dir($realPath)) {
            http_response_code(404);
            exit('File tidak ditemukan.');
        }

        $filename = basename($realPath);
        $filesize = filesize($realPath);

        // Bersihkan output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . $filesize);

        $fp = @fopen($realPath, 'rb');
        if ($fp) {
            while (!feof($fp)) {
                echo fread($fp, 64 * 1024);
                flush();
            }
            fclose($fp);
        }
        exit;
    }

    /**
     * Mengemas folder menjadi ZIP temporary, mengirimkannya ke browser, lalu membersihkan file temporary.
     */
    public static function downloadFolderAsZip(string $relativePath): void
    {
        $realPath = Security::resolvePath($relativePath, true);
        if ($realPath === null || !is_dir($realPath)) {
            http_response_code(404);
            exit('Folder tidak ditemukan.');
        }

        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            exit('Ekstensi PHP ZipArchive tidak aktif pada server.');
        }

        $folderName = basename($realPath);
        if ($folderName === '' || $realPath === Security::getRoot()) {
            $folderName = 'root_archive';
        }

        $tempDir = defined('TEMP_PATH') ? TEMP_PATH : (__DIR__ . '/../storage/temp');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        // Bersihkan arsip temporary lama (> 1 jam) untuk menghemat ruang disk
        $oldFiles = @glob($tempDir . DIRECTORY_SEPARATOR . 'dl_*.zip');
        if ($oldFiles) {
            $now = time();
            foreach ($oldFiles as $oldFile) {
                if ($now - @filemtime($oldFile) > 3600) {
                    @unlink($oldFile);
                }
            }
        }

        $zipTempFile = $tempDir . DIRECTORY_SEPARATOR . 'dl_' . bin2hex(random_bytes(8)) . '.zip';

        // Pastikan file temporary selalu terhapus bahkan jika koneksi terputus (abort)
        register_shutdown_function(function () use ($zipTempFile) {
            if (file_exists($zipTempFile)) {
                @unlink($zipTempFile);
            }
        });

        $zip = new ZipArchive();
        if ($zip->open($zipTempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            exit('Gagal membuat arsip ZIP temporary.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $baseLen = strlen($realPath);

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $subPath = substr($filePath, $baseLen + 1);
            $zipEntryPath = str_replace('\\', '/', $subPath);

            if ($file->isDir()) {
                $zip->addEmptyDir($zipEntryPath);
            } else {
                $zip->addFile($filePath, $zipEntryPath);
            }
        }

        $zip->close();

        // Stream file ke klien
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . addslashes($folderName) . '.zip"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($zipTempFile));

        readfile($zipTempFile);
        flush();

        // Bersihkan file temporary langsung
        @unlink($zipTempFile);
        exit;
    }

    /**
     * Membaca isi berkas teks untuk dimuat ke editor atau pratinjau.
     */
    public static function getFileContent(string $relativePath): array
    {
        $realPath = Security::resolvePath($relativePath, true);
        if ($realPath === null || !is_file($realPath)) {
            return ['success' => false, 'message' => 'Berkas tidak ditemukan atau akses ditolak.'];
        }

        $fileName = basename($realPath);
        if (!Security::isTextPreviewable($fileName)) {
            return ['success' => false, 'message' => 'Berkas biner tidak didukung untuk editor teks.'];
        }

        $size = filesize($realPath);
        $maxEditSize = 5 * 1024 * 1024; // 5 MB limit
        if ($size > $maxEditSize) {
            return [
                'success' => false,
                'message' => 'Ukuran berkas terlalu besar (' . Security::formatBytes($size) . '). Batas maksimal editor adalah 5 MB.'
            ];
        }

        $content = @file_get_contents($realPath);
        if ($content === false) {
            return ['success' => false, 'message' => 'Gagal membaca isi berkas dari server.'];
        }

        // Pastikan encoding UTF-8
        if (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
            if (function_exists('mb_convert_encoding')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1, Windows-1252, ASCII');
            } elseif (function_exists('utf8_encode')) {
                $content = utf8_encode($content);
            }
        }

        return [
            'success'        => true,
            'name'           => $fileName,
            'virtual_path'   => Security::toVirtualPath($realPath),
            'content'        => $content,
            'size'           => $size,
            'formatted_size' => Security::formatBytes($size),
            'is_large'       => ($size > 1024 * 1024), // Peringatan jika > 1 MB
            'extension'      => strtolower(pathinfo($fileName, PATHINFO_EXTENSION)),
            'is_writable'    => is_writable($realPath)
        ];
    }

    /**
     * Menyimpan isi berkas yang diedit melalui file editor.
     */
    public static function saveFileContent(string $relativePath, string $content): array
    {
        if (Security::isProtected($relativePath)) {
            return ['success' => false, 'message' => 'Berkas ini dilindungi dan tidak dapat dimodifikasi.'];
        }

        $realPath = Security::resolvePath($relativePath, false);
        if ($realPath === null) {
            return ['success' => false, 'message' => 'Lokasi berkas tidak valid atau akses ditolak.'];
        }

        $parent = dirname($realPath);
        if (!is_dir($parent) || !Security::isInsideRoot($parent, Security::getRoot())) {
            return ['success' => false, 'message' => 'Direktori induk tidak ditemukan atau di luar batas akses.'];
        }

        if (file_exists($realPath) && !is_writable($realPath)) {
            return ['success' => false, 'message' => 'Berkas tidak dapat ditulis (Permission Denied).'];
        }

        if (@file_put_contents($realPath, $content) === false) {
            return ['success' => false, 'message' => 'Gagal menyimpan perubahan berkas. Periksa izin disk server.'];
        }

        $virtual = Security::toVirtualPath($realPath);
        Logger::log('EDIT', $virtual, 'SUCCESS', Security::formatBytes(strlen($content)));

        return [
            'success'        => true,
            'message'        => 'Perubahan berhasil disimpan.',
            'size'           => filesize($realPath),
            'formatted_size' => Security::formatBytes(filesize($realPath))
        ];
    }

    /**
     * Mengubah hak akses (CHMOD) file atau folder.
     */
    public static function changePermissions(string $relativePath, $mode): array
    {
        if (Security::isProtected($relativePath)) {
            return ['success' => false, 'message' => 'Item ini dilindungi dan hak aksesnya tidak boleh diubah.'];
        }

        $realPath = Security::resolvePath($relativePath, true);
        if ($realPath === null || !file_exists($realPath)) {
            return ['success' => false, 'message' => 'Item tidak ditemukan atau akses ditolak.'];
        }

        if (strcasecmp($realPath, Security::getRoot()) === 0) {
            return ['success' => false, 'message' => 'Tidak dapat mengubah hak akses direktori root.'];
        }

        if (is_string($mode)) {
            $mode = trim($mode);
            $octalInt = octdec($mode);
        } else {
            $octalInt = (int)$mode;
        }

        @chmod($realPath, $octalInt);

        $stat = @stat($realPath);
        $currentPerms = $stat ? Security::formatPermissions($stat['mode']) : null;
        $virtual = Security::toVirtualPath($realPath);

        Logger::log('CHMOD', $virtual, 'SUCCESS', 'Mode: ' . sprintf('%04o', $octalInt & 0777));

        $note = '';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $note = ' (Catatan: Pada sistem Windows, atribut chmod difokuskan pada status Read-Only).';
        }

        return [
            'success'     => true,
            'message'     => 'Hak akses berhasil diperbarui ke ' . sprintf('%04o', $octalInt & 0777) . '.' . $note,
            'permissions' => $currentPerms
        ];
    }

    /**
     * Menghapus banyak file/folder sekaligus (Bulk Delete).
     */
    public static function bulkDelete(array $paths): array
    {
        if (empty($paths)) {
            return ['success' => false, 'message' => 'Tidak ada item yang dipilih untuk dihapus.'];
        }

        $deleted = 0;
        $failed = [];

        foreach ($paths as $path) {
            $res = self::deleteItem($path);
            if ($res['success']) {
                $deleted++;
            } else {
                $failed[] = basename($path) . ' (' . ($res['message'] ?? 'Gagal') . ')';
            }
        }

        $msg = "$deleted item berhasil dihapus.";
        if (!empty($failed)) {
            $msg .= ' Beberapa item gagal: ' . implode(', ', $failed);
        }

        return [
            'success'       => ($deleted > 0),
            'message'       => $msg,
            'deleted_count' => $deleted,
            'failed_count'  => count($failed),
            'failed'        => $failed
        ];
    }

    /**
     * Menyalin banyak file/folder ke direktori tujuan sekaligus (Bulk Copy).
     */
    public static function bulkCopy(array $paths, string $destinationDir): array
    {
        if (empty($paths)) {
            return ['success' => false, 'message' => 'Tidak ada item yang dipilih untuk disalin.'];
        }

        $copied = 0;
        $failed = [];

        foreach ($paths as $path) {
            $res = self::copyItem($path, $destinationDir);
            if ($res['success']) {
                $copied++;
            } else {
                $failed[] = basename($path) . ' (' . ($res['message'] ?? 'Gagal') . ')';
            }
        }

        $msg = "$copied item berhasil disalin.";
        if (!empty($failed)) {
            $msg .= ' Beberapa item gagal: ' . implode(', ', $failed);
        }

        return [
            'success'      => ($copied > 0),
            'message'      => $msg,
            'copied_count' => $copied,
            'failed_count' => count($failed),
            'failed'       => $failed
        ];
    }

    /**
     * Memindahkan banyak file/folder ke direktori tujuan sekaligus (Bulk Move).
     */
    public static function bulkMove(array $paths, string $destinationDir): array
    {
        if (empty($paths)) {
            return ['success' => false, 'message' => 'Tidak ada item yang dipilih untuk dipindahkan.'];
        }

        $moved = 0;
        $failed = [];

        foreach ($paths as $path) {
            $res = self::moveItem($path, $destinationDir);
            if ($res['success']) {
                $moved++;
            } else {
                $failed[] = basename($path) . ' (' . ($res['message'] ?? 'Gagal') . ')';
            }
        }

        $msg = "$moved item berhasil dipindahkan.";
        if (!empty($failed)) {
            $msg .= ' Beberapa item gagal: ' . implode(', ', $failed);
        }

        return [
            'success'     => ($moved > 0),
            'message'     => $msg,
            'moved_count' => $moved,
            'failed_count'=> count($failed),
            'failed'      => $failed
        ];
    }

    /**
     * Mengunduh banyak file/folder yang dipilih sekaligus dalam satu berkas ZIP (Bulk Download).
     */
    public static function downloadMultipleAsZip(array $paths, string $zipName = 'download.zip', bool $streamToOutput = true)
    {
        if (empty($paths)) {
            if (!$streamToOutput) return null;
            http_response_code(400);
            exit('Tidak ada item yang dipilih untuk diunduh.');
        }

        $tempDir = defined('TEMP_PATH') ? TEMP_PATH : sys_get_temp_dir();
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $zipTempFile = $tempDir . DIRECTORY_SEPARATOR . 'bulk_' . bin2hex(random_bytes(8)) . '.zip';

        register_shutdown_function(function () use ($zipTempFile) {
            if (file_exists($zipTempFile)) {
                @unlink($zipTempFile);
            }
        });

        $zip = new ZipArchive();
        if ($zip->open($zipTempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            if (!$streamToOutput) return null;
            http_response_code(500);
            exit('Gagal membuat arsip ZIP temporary.');
        }

        $added = 0;
        foreach ($paths as $path) {
            $realPath = Security::resolvePath($path, true);
            if ($realPath === null || !file_exists($realPath)) continue;

            $entryName = basename($realPath);
            if (is_dir($realPath)) {
                $added += self::addFolderToZip($zip, $realPath, $entryName);
            } else {
                $zip->addFile($realPath, $entryName);
                $added++;
            }
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipTempFile);
            if (!$streamToOutput) return null;
            http_response_code(400);
            exit('Tidak ada file valid yang dapat diunduh.');
        }

        Logger::log('DOWNLOAD', 'Bulk Download: ' . count($paths) . ' items', 'SUCCESS');

        if (!$streamToOutput) {
            return $zipTempFile;
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $zipName);
        if (!str_ends_with(strtolower($safeName), '.zip')) $safeName .= '.zip';

        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . addslashes($safeName) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($zipTempFile));

        readfile($zipTempFile);
        flush();

        @unlink($zipTempFile);
        exit;
    }
}
