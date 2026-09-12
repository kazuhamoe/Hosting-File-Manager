<?php
/**
 * ZIP Archive & Extraction Engine
 * Menangani validasi integritas, proteksi ZipSlip / Path Traversal, symlink protection,
 * dan strategi penanganan konflik (Overwrite, Skip, Rename).
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access denied.');
}

require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Logger.php';

class ZipManager
{
    /**
     * Memvalidasi seluruh entry di dalam ZIP sebelum melakukan ekstraksi.
     * Mencegah:
     * - Path traversal (../, ..\, /etc/..., C:\...)
     * - Destination escape (Zip Slip)
     * - Penulisan ke protected files
     * - Symlink abuse
     *
     * @return array [ 'valid' => bool, 'message' => string, 'entries' => array ]
     */
    public static function validateArchiveEntries(ZipArchive $zip, string $destinationReal): array
    {
        $root = Security::getRoot();
        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) {
                return [
                    'valid'   => false,
                    'message' => 'Gagal membaca metadata entry arsip ZIP.'
                ];
            }

            $entryName = $stat['name'];

            // Deteksi Symbolic Link (Symlink Abuse Protection)
            if (method_exists($zip, 'getExternalAttributesIndex')) {
                $opsys = 0;
                $attr = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attr)) {
                    if ($opsys === ZipArchive::OPSYS_UNIX) {
                        $mode = ($attr >> 16) & 0xFFFF;
                        if (($mode & 0120000) === 0120000) {
                            return [
                                'valid'   => false,
                                'message' => 'Arsip ditolak: Terdeteksi symbolic link berbahaya yang dilarang.'
                            ];
                        }
                    }
                }
            }

            // 2. Hapus dan tolak null byte
            if (strpos($entryName, chr(0)) !== false) {
                Logger::log('EXTRACT_REJECT', Security::toVirtualPath($destinationReal), 'BLOCKED', "Null byte terdeteksi: entry={$entryName}");
                return [
                    'valid'   => false,
                    'message' => 'Arsip berisi entry dengan null byte ilegal.'
                ];
            }

            // 3. Deteksi URL encoded traversal di dalam nama entry ZIP (%2e%2e, %2f, %5c)
            $checkDecoded = $entryName;
            while (strpos($checkDecoded, '%') !== false) {
                $decoded = rawurldecode($checkDecoded);
                if ($decoded === $checkDecoded) break;
                if (strpos($decoded, '..') !== false || strpos($decoded, '/') !== false || strpos($decoded, '\\') !== false) {
                    Logger::log('EXTRACT_REJECT', Security::toVirtualPath($destinationReal), 'BLOCKED', "Encoded traversal terdeteksi: raw={$entryName}, decoded={$decoded}");
                    return [
                        'valid'   => false,
                        'message' => 'Arsip ditolak karena mengandung karakter traversal terenkode.'
                    ];
                }
                $checkDecoded = $decoded;
            }

            // 4. Normalisasi separator
            $normalizedEntry = str_replace('\\', '/', $entryName);

            // 5. Deteksi absolute path (Linux / atau Windows drive letter C:)
            if (str_starts_with($normalizedEntry, '/') || preg_match('/^[a-zA-Z]:/', $normalizedEntry)) {
                Logger::log('EXTRACT_REJECT', Security::toVirtualPath($destinationReal), 'BLOCKED', "Absolute path terdeteksi: entry={$entryName}");
                return [
                    'valid'   => false,
                    'message' => 'Arsip ditolak karena mengandung absolute path yang tidak diizinkan.'
                ];
            }

            // 6. Hapus leading ./ atau multiple ./ yang tidak diperlukan
            while (str_starts_with($normalizedEntry, './')) {
                $normalizedEntry = substr($normalizedEntry, 2);
            }
            $normalizedEntry = ltrim($normalizedEntry, '/');

            // 7. Cek path traversal dengan tokenisasi & tracking kedalaman ($depth)
            $segments = explode('/', $normalizedEntry);
            $safeSegments = [];
            $depth = 0;
            foreach ($segments as $seg) {
                $seg = trim($seg);
                if ($seg === '' || $seg === '.') continue;
                if ($seg === '..' || $seg === '..\\') {
                    $depth--;
                    if ($depth < 0) {
                        Logger::log('EXTRACT_REJECT', Security::toVirtualPath($destinationReal), 'BLOCKED', "Zip Slip traversal (..) terdeteksi: entry={$entryName}");
                        return [
                            'valid'   => false,
                            'message' => 'Arsip ditolak: Terdeteksi upaya Zip Slip / Path Traversal berbahaya.'
                        ];
                    }
                    array_pop($safeSegments);
                } else {
                    if (preg_match('/^[a-zA-Z]:$/', $seg)) {
                        Logger::log('EXTRACT_REJECT', Security::toVirtualPath($destinationReal), 'BLOCKED', "Windows drive segment terdeteksi: entry={$entryName}");
                        return [
                            'valid'   => false,
                            'message' => 'Arsip ditolak karena mengandung Windows drive path di dalam entry.'
                        ];
                    }
                    $depth++;
                    $safeSegments[] = $seg;
                }
            }

            $cleanEntry = implode('/', $safeSegments);
            if (str_ends_with($entryName, '/') || str_ends_with($entryName, '\\')) {
                $cleanEntry .= '/';
            }

            // 8. Hitung calon absolute target path
            $candidateTarget = $destinationReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanEntry);

            // 9. Periksa boundary kanonikal terhadap ALLOWED_ROOT
            if (!Security::isInsideRoot($candidateTarget, $root)) {
                Logger::log('EXTRACT_REJECT', Security::toVirtualPath($destinationReal), 'BLOCKED', "Escapes ALLOWED_ROOT: entry={$entryName}, candidate={$candidateTarget}");
                return [
                    'valid'   => false,
                    'message' => 'Arsip ditolak: Target ekstraksi berada di luar batas direktori yang diizinkan.'
                ];
            }

            // 10. Hitung virtual path relatif terhadap ALLOWED_ROOT
            $normalizedRel = Security::normalizeRelativePath(Security::toVirtualPath($candidateTarget));

            // 11. Periksa proteksi file secara akurat (Anti False-Positive)
            // Hanya tolak jika entry benar-benar mencoba menimpa file terproteksi sistem
            $protCheck = Security::checkExtractProtection($candidateTarget, $normalizedRel ?? '');
            if ($protCheck['is_protected']) {
                Logger::log('EXTRACT_REJECT', Security::toVirtualPath($destinationReal), 'BLOCKED', "Protected file conflict: entry={$entryName}, rule={$protCheck['reason']}");
                return [
                    'valid'   => false,
                    'message' => 'Arsip berisi file yang mencoba menimpa file terproteksi sistem.'
                ];
            }

            $isDir = str_ends_with($cleanEntry, '/');
            $entries[] = [
                'index'          => $i,
                'name'           => $cleanEntry,
                'is_dir'         => $isDir,
                'target_rel'     => $normalizedRel,
                'candidate_path' => $candidateTarget,
                'size'           => $stat['size']
            ];
        }

        return [
            'valid'   => true,
            'message' => 'Arsip valid.',
            'entries' => $entries
        ];
    }

    /**
     * Mengekstrak arsip ZIP ke destinasi yang dipilih dengan strategi penanganan konflik.
     *
     * @param string $zipRelativePath Path virtual file ZIP
     * @param string $destRelativePath Path virtual folder tujuan
     * @param string $conflictStrategy 'overwrite', 'skip', atau 'rename'
     */
    public static function extractZip(
        string $zipRelativePath,
        string $destRelativePath,
        string $conflictStrategy = 'overwrite'
    ): array {
        if (!class_exists('ZipArchive')) {
            return [
                'success' => false,
                'message' => 'Ekstensi PHP ZipArchive tidak aktif pada server hosting.'
            ];
        }

        $zipReal = Security::resolvePath($zipRelativePath, true);
        if ($zipReal === null || !is_file($zipReal)) {
            return [
                'success' => false,
                'message' => 'File ZIP tidak ditemukan.'
            ];
        }

        // Berikan izin penuh pada file zip agar dapat dibaca/diekstrak tanpa kendala umask server
        @chmod($zipReal, 0777);

        $destReal = Security::resolvePath($destRelativePath, false);
        if ($destReal === null) {
            return [
                'success' => false,
                'message' => 'Folder tujuan ekstraksi tidak valid atau berada di luar root.'
            ];
        }

        if (!file_exists($destReal)) {
            if (!@mkdir($destReal, 0777, true) && !is_dir($destReal)) {
                return [
                    'success' => false,
                    'message' => 'Gagal membuat folder tujuan ekstraksi. Periksa permission server.'
                ];
            }
            @chmod($destReal, 0777);
        } else {
            @chmod($destReal, 0777);
        }
        $destReal = realpath($destReal);
        if ($destReal === false || !is_dir($destReal)) {
            return [
                'success' => false,
                'message' => 'Folder tujuan ekstraksi tidak valid.'
            ];
        }

        $zip = new ZipArchive();
        $openRes = $zip->open($zipReal);
        if ($openRes !== true) {
            return [
                'success' => false,
                'message' => 'Gagal membuka arsip ZIP. Arsip mungkin corrupt atau diproteksi password.'
            ];
        }

        // Tahap 1: Validasi ketat seluruh entry
        $validation = self::validateArchiveEntries($zip, $destReal);
        if (!$validation['valid']) {
            $zip->close();
            Logger::log('EXTRACT', Security::toVirtualPath($zipReal), 'BLOCKED', $validation['message']);
            return [
                'success' => false,
                'message' => $validation['message']
            ];
        }

        $entries = $validation['entries'];
        $extractedCount = 0;
        $skippedCount = 0;
        $renamedCount = 0;

        // Tahap 2: Ekstraksi aman entry per entry
        foreach ($entries as $entry) {
            $targetPath = $entry['candidate_path'];

            if ($entry['is_dir']) {
                if (!is_dir($targetPath)) {
                    if (!@mkdir($targetPath, 0777, true) && !is_dir($targetPath)) {
                        $zip->close();
                        return [
                            'success' => false,
                            'message' => 'Gagal membuat direktori saat ekstraksi. Periksa permission server.'
                        ];
                    }
                }
                @chmod($targetPath, 0777);
                continue;
            }

            // Pastikan parent directory ada
            $parentDir = dirname($targetPath);
            if (!is_dir($parentDir)) {
                if (!@mkdir($parentDir, 0777, true) && !is_dir($parentDir)) {
                    $zip->close();
                    return [
                        'success' => false,
                        'message' => 'Gagal membuat subdirektori untuk ekstraksi file.'
                    ];
                }
                @chmod($parentDir, 0777);
            }

            // Penanganan jika file sudah ada
            if (file_exists($targetPath)) {
                // Jangan pernah menimpa file credentials.json yang sudah ada agar akun admin tidak ter-reset
                if (strcasecmp(basename($targetPath), 'credentials.json') === 0) {
                    $skippedCount++;
                    continue;
                }

                // Jika berkas yang akan ditimpa adalah config.php dan belum ada credentials.json,
                // amankan hash kredensial lama ke storage/credentials.json agar akun admin tidak hilang
                if (strcasecmp(basename($targetPath), 'config.php') === 0) {
                    $possibleCredFile = dirname($targetPath) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'credentials.json';
                    if (!file_exists($possibleCredFile)) {
                        $oldConfContent = @file_get_contents($targetPath);
                        if ($oldConfContent) {
                            $uM = []; $hM = [];
                            preg_match("/define\s*\(\s*['\"]AUTH_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $oldConfContent, $uM);
                            preg_match("/define\s*\(\s*['\"]AUTH_PASS_HASH['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $oldConfContent, $hM);
                            $oldUser = !empty($uM[1]) ? $uM[1] : 'admin';
                            $oldHash = !empty($hM[1]) ? $hM[1] : '';
                            if (!empty($oldHash)) {
                                if (!is_dir(dirname($possibleCredFile))) {
                                    @mkdir(dirname($possibleCredFile), 0777, true);
                                }
                                @file_put_contents($possibleCredFile, json_encode([
                                    'username' => $oldUser,
                                    'password_hash' => $oldHash,
                                    'updated_at' => date('Y-m-d H:i:s'),
                                    'updated_by_ip' => 'auto-migrated-zip-extract'
                                ], JSON_PRETTY_PRINT));
                                @chmod($possibleCredFile, 0777);
                            }
                        }
                    }
                }

                if ($conflictStrategy === 'skip') {
                    $skippedCount++;
                    continue;
                } elseif ($conflictStrategy === 'rename') {
                    $targetPath = self::generateUniqueName($targetPath);
                    $renamedCount++;
                }
                // Jika 'overwrite', biarkan tertimpa (file_put_contents akan me-replace)
            }

            // Ekstrak konten stream
            $stream = $zip->getStream($entry['name']);
            if (!$stream) {
                // Lewati atau batalkan jika tidak bisa dibaca
                continue;
            }

            $outFp = @fopen($targetPath, 'wb');
            if (!$outFp) {
                fclose($stream);
                $zip->close();
                return [
                    'success' => false,
                    'message' => 'Gagal menulis file tujuan ekstraksi.'
                ];
            }

            while (!feof($stream)) {
                fwrite($outFp, fread($stream, 64 * 1024));
            }

            fclose($stream);
            fclose($outFp);
            // Berikan izin penuh (0777 / full centang) pada berkas hasil ekstraksi
            @chmod($targetPath, 0777);
            $extractedCount++;
        }

        $zip->close();

        $logMsg = "Extracted: $extractedCount, Skipped: $skippedCount, Renamed: $renamedCount";
        Logger::log('EXTRACT', Security::toVirtualPath($zipReal) . " -> " . Security::toVirtualPath($destReal), 'SUCCESS', $logMsg);

        return [
            'success'         => true,
            'message'         => "Arsip berhasil diekstrak ($extractedCount file diekstrak" . ($skippedCount > 0 ? ", $skippedCount dilewati" : "") . ($renamedCount > 0 ? ", $renamedCount diubah nama" : "") . ").",
            'extracted_count' => $extractedCount,
            'skipped_count'   => $skippedCount,
            'renamed_count'   => $renamedCount
        ];
    }

    /**
     * Menghasilkan nama unik jika terjadi konflik nama file (rename strategy).
     */
    private static function generateUniqueName(string $filePath): string
    {
        $dir = dirname($filePath);
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $extSuffix = ($ext !== '') ? '.' . $ext : '';

        $counter = 1;
        do {
            $newPath = $dir . DIRECTORY_SEPARATOR . $filename . '_copy' . ($counter > 1 ? "_$counter" : "") . $extSuffix;
            $counter++;
        } while (file_exists($newPath));

        return $newPath;
    }

    /**
     * Membuat arsip ZIP baru dari sekumpulan file dan folder yang dipilih (Compress).
     *
     * @param array $items Array relative/virtual path dari item yang dipilih
     * @param string $destinationDir Direktori target tempat menyimpan file ZIP
     * @param string $zipName Nama file ZIP yang diinginkan (contoh: 'backup.zip')
     * @return array [ 'success' => bool, 'message' => string, 'zip_path' => string, ... ]
     */
    public static function createZip(array $items, string $destinationDir, string $zipName): array
    {
        if (empty($items)) {
            return ['success' => false, 'message' => 'Tidak ada berkas atau folder yang dipilih untuk dikompres.'];
        }

        $destReal = Security::resolvePath($destinationDir, true);
        if ($destReal === null || !is_dir($destReal)) {
            return ['success' => false, 'message' => 'Direktori tujuan kompresi tidak valid atau akses ditolak.'];
        }

        $zipName = trim($zipName);
        if ($zipName === '') {
            $zipName = 'archive_' . date('Ymd_His') . '.zip';
        }
        if (!str_ends_with(strtolower($zipName), '.zip')) {
            $zipName .= '.zip';
        }

        $sanitizedZipName = Security::sanitizeFilename($zipName);
        if (!Security::isValidFilename($sanitizedZipName)) {
            return ['success' => false, 'message' => 'Nama arsip ZIP tidak valid.'];
        }

        $zipFilePath = $destReal . DIRECTORY_SEPARATOR . $sanitizedZipName;
        $virtualZipPath = Security::toVirtualPath($zipFilePath);

        if (Security::isProtected($virtualZipPath)) {
            return ['success' => false, 'message' => 'Dilarang menimpa file terproteksi sistem.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['success' => false, 'message' => 'Gagal membuat berkas ZIP. Periksa permission direktori server.'];
        }

        $addedCount = 0;
        foreach ($items as $itemPath) {
            $itemReal = Security::resolvePath($itemPath, true);
            if ($itemReal === null || !file_exists($itemReal)) {
                continue;
            }

            // Mencegah memasukkan zip target ke dalam arsip itu sendiri
            if (strcasecmp($itemReal, $zipFilePath) === 0) {
                continue;
            }

            $entryBase = basename($itemReal);

            if (is_dir($itemReal)) {
                $zip->addEmptyDir($entryBase);
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($itemReal, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                $baseLen = strlen($itemReal);
                foreach ($iterator as $file) {
                    $subPath = substr($file->getRealPath(), $baseLen + 1);
                    $zipEntry = $entryBase . '/' . str_replace('\\', '/', $subPath);
                    if ($file->isDir()) {
                        $zip->addEmptyDir($zipEntry);
                    } else {
                        $zip->addFile($file->getRealPath(), $zipEntry);
                    }
                    $addedCount++;
                }
            } else {
                $zip->addFile($itemReal, $entryBase);
                $addedCount++;
            }
        }

        $zip->close();
        if (file_exists($zipFilePath)) {
            @chmod($zipFilePath, 0777);
        }

        if ($addedCount === 0) {
            if (file_exists($zipFilePath)) {
                @unlink($zipFilePath);
            }
            return ['success' => false, 'message' => 'Tidak ada berkas valid yang dapat ditambahkan ke arsip.'];
        }

        Logger::log('COMPRESS', $virtualZipPath, 'SUCCESS', "$addedCount item dikompres ke $sanitizedZipName");

        return [
            'success'        => true,
            'message'        => "Arsip '$sanitizedZipName' berhasil dibuat ($addedCount item).",
            'zip_path'       => $virtualZipPath,
            'name'           => $sanitizedZipName,
            'size'           => filesize($zipFilePath),
            'formatted_size' => Security::formatBytes(filesize($zipFilePath))
        ];
    }
}
