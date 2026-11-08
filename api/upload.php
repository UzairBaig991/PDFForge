<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}
verify_csrf();

$supportedTools = [
    'pdf-to-jpg', 'pdf-to-text', 'jpg-to-pdf', 'pdf-to-word', 'pdf-to-excel',
    'pdf-to-ppt', 'merge-pdf', 'split-pdf', 'compress-pdf', 'rotate-pdf',
    'watermark-pdf', 'protect-pdf', 'unlock-pdf',
];
$tool = (string) ($_POST['tool'] ?? '');
if (!in_array($tool, $supportedTools, true)) {
    json_response(['success' => false, 'message' => 'That PDF tool is not available.'], 422);
}

$uploads = $_FILES['files'] ?? $_FILES['pdf'] ?? null;
if (!is_array($uploads) || !isset($uploads['name'])) {
    json_response(['success' => false, 'message' => 'Choose a file to upload.'], 400);
}
if (!is_array($uploads['name'])) {
    foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
        $uploads[$key] = [$uploads[$key] ?? null];
    }
}
$fileCount = count($uploads['name']);
$multipleAllowed = in_array($tool, ['jpg-to-pdf', 'merge-pdf'], true);
if ($fileCount < 1 || $fileCount > 20 || (!$multipleAllowed && $fileCount !== 1)) {
    json_response(['success' => false, 'message' => $tool === 'merge-pdf'
        ? 'Select between 2 and 20 PDF files to merge.'
        : 'This tool accepts one file at a time.'], 422);
}
if ($tool === 'merge-pdf' && $fileCount < 2) {
    json_response(['success' => false, 'message' => 'Select at least two PDF files to merge.'], 422);
}

$maxSize = (int) app_config('max_upload_size');
$totalSize = 0;
$validFiles = [];
$imageTool = $tool === 'jpg-to-pdf';
foreach ($uploads['name'] as $index => $originalName) {
    $error = (int) ($uploads['error'][$index] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        $status = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 413 : 400;
        json_response(['success' => false, 'message' => $status === 413
            ? 'A selected file exceeds the server upload limit.'
            : 'Choose valid files to upload.'], $status);
    }
    $path = (string) ($uploads['tmp_name'][$index] ?? '');
    $size = (int) ($uploads['size'][$index] ?? 0);
    $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
    $signature = is_uploaded_file($path) ? file_get_contents($path, false, null, 0, 8) : false;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $valid = $imageTool
        ? in_array($extension, ['jpg', 'jpeg', 'png'], true)
            && in_array($mime, ['image/jpeg', 'image/png'], true)
        : $extension === 'pdf' && $signature !== false
            && str_starts_with($signature, '%PDF-') && $mime === 'application/pdf';
    if (!$valid) {
        json_response(['success' => false, 'message' => $imageTool
            ? 'Choose valid JPG or PNG image files.'
            : 'That file does not appear to be a valid PDF.'], 415);
    }
    if ($size < 8) {
        json_response(['success' => false, 'message' => 'A selected file is too small to be valid.'], 415);
    }
    $totalSize += $size;
    if ($totalSize > $maxSize) {
        json_response(['success' => false, 'message' => 'The combined upload exceeds ' . human_size($maxSize) . '.'], 413);
    }
    $validFiles[] = [
        'name' => (string) $originalName,
        'path' => $path,
        'size' => $size,
        'mime' => (string) $mime,
        'extension' => $extension,
    ];
}

$temporaryDirectory = null;
$storedPath = null;
try {
    ensure_storage_directories();
    $temporaryDirectory = APP_ROOT . '/storage/temp/upload-' . bin2hex(random_bytes(16));
    if (!mkdir($temporaryDirectory, 0700, true)) {
        throw new RuntimeException('Temporary upload storage is unavailable.');
    }
    if (count($validFiles) > 1) {
        $bundlePath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'inputs.zip';
        $archive = new ZipArchive();
        if ($archive->open($bundlePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The selected files could not be packaged for processing.');
        }
        foreach ($validFiles as $index => $file) {
            $archiveName = sprintf('input-%02d.%s', $index + 1, $file['extension']);
            if (!$archive->addFile($file['path'], $archiveName)) {
                $archive->close();
                throw new RuntimeException('A selected file could not be packaged for processing.');
            }
        }
        $archive->close();
        $storedPath = APP_ROOT . '/storage/uploads/' . bin2hex(random_bytes(16)) . '.zip';
        if (!rename($bundlePath, $storedPath)) {
            throw new RuntimeException('The uploaded files could not be stored securely.');
        }
        $safeName = count($validFiles) . '-inputs.zip';
        $mimeType = 'application/zip';
    } else {
        $file = $validFiles[0];
        $storageExtension = $imageTool ? $file['extension'] : 'pdf';
        $storedPath = APP_ROOT . '/storage/uploads/' . bin2hex(random_bytes(16)) . '.' . $storageExtension;
        if (!move_uploaded_file($file['path'], $storedPath)) {
            throw new RuntimeException('The uploaded file could not be stored securely.');
        }
        $safeName = basename(str_replace('\\', '/', $file['name']));
        $safeName = preg_replace('/[\x00-\x1F\x7F]/u', '', $safeName) ?: 'document.' . $storageExtension;
        $safeName = mb_substr($safeName, 0, 255);
        $mimeType = $file['mime'];
    }

    $expires = date('Y-m-d H:i:s', time() + (int) app_config('retention_seconds'));
    $user = current_user();
    $fileId = bin2hex(random_bytes(16));
    $jobId = bin2hex(random_bytes(16));
    $pdo = db();
    $rateLimit = $pdo->prepare(
        'SELECT COUNT(*) FROM files WHERE session_hash = :session_hash AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );
    $rateLimit->execute(['session_hash' => session_owner_hash()]);
    if ((int) $rateLimit->fetchColumn() >= 20) {
        json_response(['success' => false, 'message' => 'Upload limit reached. Please try again later.'], 429);
    }
    $pdo->beginTransaction();
    $insertFile = $pdo->prepare(
        'INSERT INTO files (id, user_id, session_hash, original_name, stored_path, mime_type, size_bytes, expires_at)
         VALUES (:id, :user_id, :session_hash, :original_name, :stored_path, :mime_type, :size_bytes, :expires_at)'
    );
    $insertFile->execute([
        'id' => $fileId,
        'user_id' => $user['id'] ?? null,
        'session_hash' => session_owner_hash(),
        'original_name' => $safeName,
        'stored_path' => $storedPath,
        'mime_type' => $mimeType,
        'size_bytes' => $totalSize,
        'expires_at' => $expires,
    ]);
    $insertJob = $pdo->prepare(
        'INSERT INTO jobs (id, file_id, user_id, session_hash, tool, status, expires_at)
         VALUES (:id, :file_id, :user_id, :session_hash, :tool, :status, :expires_at)'
    );
    $insertJob->execute([
        'id' => $jobId,
        'file_id' => $fileId,
        'user_id' => $user['id'] ?? null,
        'session_hash' => session_owner_hash(),
        'tool' => $tool,
        'status' => 'QUEUED',
        'expires_at' => $expires,
    ]);
    $pdo->commit();
    json_response(['success' => true, 'message' => 'Files uploaded securely.', 'file_id' => $fileId, 'job_id' => $jobId], 201);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($storedPath !== null && is_file($storedPath)) {
        unlink($storedPath);
    }
    log_app_error($error, 'upload');
    json_response(['success' => false, 'message' => 'The upload could not be saved. Check the database and storage configuration.'], 500);
} finally {
    if ($temporaryDirectory !== null && is_dir($temporaryDirectory)) {
        foreach (glob($temporaryDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($temporaryDirectory);
    }
}
