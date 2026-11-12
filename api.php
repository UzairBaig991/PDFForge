<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
$action = strtolower(pathinfo($requestPath, PATHINFO_FILENAME));
$handlers = [
    'upload' => 'api_upload',
    'process' => 'api_process',
    'status' => 'api_status',
    'download' => 'api_download',
    'delete' => 'api_delete',
    'tools' => 'api_tools',
];
if (!isset($handlers[$action])) {
    json_response(['success' => false, 'message' => 'The requested API endpoint was not found.'], 404);
}
$handlers[$action]();

function api_upload(): void
{
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
        $pdo = db();
        $rateLimit = $pdo->prepare(
            'SELECT COUNT(*) FROM files WHERE session_hash = :session_hash AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $rateLimit->execute(['session_hash' => session_owner_hash()]);
        if ((int) $rateLimit->fetchColumn() >= 20) {
            json_response(['success' => false, 'message' => 'Upload limit reached. Please try again later.'], 429);
        }

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

        $expiry = $pdo->prepare('SELECT DATE_ADD(NOW(), INTERVAL :retention_seconds SECOND)');
        $expiry->execute(['retention_seconds' => (int) app_config('retention_seconds')]);
        $expires = $expiry->fetchColumn();
        if (!is_string($expires)) {
            throw new RuntimeException('File expiration could not be calculated.');
        }
        $user = current_user();
        $fileId = bin2hex(random_bytes(16));
        $jobId = bin2hex(random_bytes(16));
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
}

function api_process(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    verify_csrf();

    $jobId = (string) ($_POST['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
        json_response(['success' => false, 'message' => 'The requested job was not found.'], 404);
    }

    $temporaryDirectory = null;
    try {
        $query = db()->prepare(
            'SELECT j.*, f.stored_path, f.original_name, f.size_bytes AS original_size
             FROM jobs j JOIN files f ON f.id = j.file_id
             WHERE j.id = :id AND j.expires_at > NOW()
               AND ((j.user_id IS NOT NULL AND j.user_id = :user_id) OR
                    (j.user_id IS NULL AND j.session_hash = :session_hash))
             LIMIT 1'
        );
        $user = current_user();
        $query->execute([
            'id' => $jobId,
            'user_id' => $user['id'] ?? null,
            'session_hash' => session_owner_hash(),
        ]);
        $job = $query->fetch();
        if (!$job) {
            json_response(['success' => false, 'message' => 'The requested job was not found or has expired.'], 404);
        }
        if ($job['status'] === 'COMPLETED') {
            json_response(['success' => true, 'status' => 'COMPLETED', 'job_id' => $jobId, 'result_name' => $job['result_name']]);
        }
        if ($job['status'] === 'PROCESSING') {
            json_response(['success' => false, 'message' => 'This job is already processing.'], 409);
        }

        $markProcessing = db()->prepare(
            "UPDATE jobs SET status = 'PROCESSING', error_message = NULL WHERE id = :id AND status IN ('QUEUED', 'FAILED')"
        );
        $markProcessing->execute(['id' => $jobId]);
        if ($markProcessing->rowCount() !== 1) {
            json_response(['success' => false, 'message' => 'This job is already processing.'], 409);
        }
        ensure_storage_directories();
        $temporaryDirectory = APP_ROOT . '/storage/temp/' . bin2hex(random_bytes(16));
        if (!mkdir($temporaryDirectory, 0700, true)) {
            throw new RuntimeException('Temporary processing storage is unavailable.');
        }

        $inputs = [];
        if (strtolower(pathinfo((string) $job['stored_path'], PATHINFO_EXTENSION)) === 'zip') {
            if (!class_exists(ZipArchive::class)) {
                throw new RuntimeException('ZIP support is not configured.');
            }
            $archive = new ZipArchive();
            if ($archive->open((string) $job['stored_path']) !== true || $archive->numFiles > 20) {
                throw new RuntimeException('The uploaded file bundle is invalid.');
            }
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entryName = (string) $archive->getNameIndex($index);
                if (!preg_match('/^input-\d{2}\.(?:pdf|jpg|jpeg|png)$/i', $entryName)) {
                    $archive->close();
                    throw new RuntimeException('The uploaded file bundle is invalid.');
                }
                $stream = $archive->getStream($entryName);
                if (!is_resource($stream)) {
                    $archive->close();
                    throw new RuntimeException('An uploaded file could not be read.');
                }
                $extension = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
                $inputPath = $temporaryDirectory . DIRECTORY_SEPARATOR . $entryName;
                $output = fopen($inputPath, 'wb');
                if ($output === false) {
                    fclose($stream);
                    $archive->close();
                    throw new RuntimeException('Temporary processing storage is unavailable.');
                }
                stream_copy_to_stream($stream, $output);
                fclose($stream);
                fclose($output);
                if ($extension === 'pdf') {
                    if (file_get_contents($inputPath, false, null, 0, 5) !== '%PDF-') {
                        $archive->close();
                        throw new RuntimeException('An uploaded file is not a valid PDF.');
                    }
                } elseif (@getimagesize($inputPath) === false) {
                    $archive->close();
                    throw new RuntimeException('An uploaded image is invalid.');
                }
                $inputs[] = $inputPath;
            }
            $archive->close();
        } else {
            $inputs[] = (string) $job['stored_path'];
        }

        if (!$inputs) {
            throw new RuntimeException('No files were available for processing.');
        }
        $baseName = pathinfo((string) $job['original_name'], PATHINFO_FILENAME);
        if (str_ends_with(strtolower($baseName), '-inputs')) {
            $baseName = 'documents';
        }
        $baseName = preg_replace('/[^a-z0-9_-]+/i', '-', $baseName) ?: 'document';
        $result = (new \PDFForge\Services\PdfToolsService())->process(
            (string) $job['tool'],
            $inputs,
            $_POST,
            $temporaryDirectory,
            $baseName
        );
        $resultName = $result['name'];
        $extension = strtolower(pathinfo($resultName, PATHINFO_EXTENSION));
        $resultPath = APP_ROOT . '/storage/processed/' . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!rename($result['path'], $resultPath)) {
            throw new RuntimeException('The converted file could not be saved.');
        }
        $size = filesize($resultPath);
        if ($size === false) {
            throw new RuntimeException('The converted file size could not be verified.');
        }
        $complete = db()->prepare(
            "UPDATE jobs SET status = 'COMPLETED', result_path = :path, result_name = :name, result_size = :size WHERE id = :id"
        );
        $complete->execute(['path' => $resultPath, 'name' => $resultName, 'size' => $size, 'id' => $jobId]);
        $usage = db()->prepare('INSERT INTO tool_usage (user_id, tool) VALUES (:user_id, :tool)');
        $usage->execute(['user_id' => $user['id'] ?? null, 'tool' => $job['tool']]);
        $response = [
            'success' => true,
            'status' => 'COMPLETED',
            'job_id' => $jobId,
            'tool' => $job['tool'],
            'result_name' => $resultName,
            'original_size' => (int) $job['original_size'],
            'result_size' => $size,
        ];
        foreach (['page_count', 'slide_count'] as $metadataKey) {
            if (isset($result[$metadataKey])) {
                $response[$metadataKey] = $result[$metadataKey];
            }
        }
        if (is_dir($temporaryDirectory)) {
            foreach (glob($temporaryDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            rmdir($temporaryDirectory);
            $temporaryDirectory = null;
        }
        json_response($response);
    } catch (Throwable $error) {
        if (isset($resultPath) && is_file($resultPath)) {
            unlink($resultPath);
        }
        if (isset($jobId) && preg_match('/^[a-f0-9]{32}$/', $jobId)) {
            try {
                $failed = db()->prepare(
                    "UPDATE jobs SET status = 'FAILED', result_path = NULL, result_name = NULL, result_size = NULL,
                     error_message = :message WHERE id = :id"
                );
                $publicMessage = $error->getMessage() === 'ZIP support is not configured.'
                    ? 'ZIP support is not configured on this PHP installation.'
                    : (($error instanceof \PDFForge\Services\PdfToolException
                        || $error->getMessage() === 'No selectable text was found. Scanned PDFs require OCR.'
                        || preg_match('/^(?:QPDF|Ghostscript|PDF processing engine) is not configured\.$/', $error->getMessage()))
                        ? $error->getMessage()
                        : 'PDF processing failed. Confirm the file is supported and the required engine is installed.');
                $failed->execute(['message' => $publicMessage, 'id' => $jobId]);
            } catch (Throwable $databaseError) {
                log_app_error($databaseError, 'job-status');
            }
        }
        log_app_error($error, 'pdf-processing');
        if (isset($temporaryDirectory) && is_dir($temporaryDirectory)) {
            foreach (glob($temporaryDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            rmdir($temporaryDirectory);
            $temporaryDirectory = null;
        }
        $publicMessage = $error->getMessage() === 'ZIP support is not configured.'
            ? 'ZIP support is not configured on this PHP installation.'
            : (($error instanceof \PDFForge\Services\PdfToolException
                || $error->getMessage() === 'No selectable text was found. Scanned PDFs require OCR.'
                || preg_match('/^(?:QPDF|Ghostscript|PDF processing engine) is not configured\.$/', $error->getMessage()))
                ? $error->getMessage()
                : 'PDF processing failed. Confirm the file is supported and the required engine is installed.');
        json_response(['success' => false, 'status' => 'FAILED', 'message' => $publicMessage], 500);
    } finally {
        if (isset($temporaryDirectory) && is_dir($temporaryDirectory)) {
            foreach (glob($temporaryDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            rmdir($temporaryDirectory);
        }
    }
}

function api_status(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    $jobId = (string) ($_GET['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
        json_response(['success' => false, 'message' => 'The requested job was not found.'], 404);
    }

    try {
        $user = current_user();
        $query = db()->prepare(
            'SELECT j.id, j.status, j.result_name, j.result_size, j.error_message
             FROM jobs j
             WHERE j.id = :id AND j.expires_at > NOW()
               AND ((j.user_id IS NOT NULL AND j.user_id = :user_id) OR
                    (j.user_id IS NULL AND j.session_hash = :session_hash))
             LIMIT 1'
        );
        $query->execute(['id' => $jobId, 'user_id' => $user['id'] ?? null, 'session_hash' => session_owner_hash()]);
        $job = $query->fetch();
        if (!$job) {
            json_response(['success' => false, 'message' => 'The requested job was not found or has expired.'], 404);
        }
        json_response(['success' => true, 'job' => $job]);
    } catch (Throwable $error) {
        log_app_error($error, 'job-status');
        json_response(['success' => false, 'message' => 'Job status is temporarily unavailable.'], 500);
    }
}

function api_download(): void
{
    $jobId = (string) ($_GET['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
        http_response_code(404);
        exit('File not found.');
    }
    try {
        $user = current_user();
        $query = db()->prepare(
            "SELECT j.result_path, j.result_name
             FROM jobs j
             WHERE j.id = :id AND j.status = 'COMPLETED' AND j.expires_at > NOW()
               AND ((j.user_id IS NOT NULL AND j.user_id = :user_id) OR
                    (j.user_id IS NULL AND j.session_hash = :session_hash))
             LIMIT 1"
        );
        $query->execute(['id' => $jobId, 'user_id' => $user['id'] ?? null, 'session_hash' => session_owner_hash()]);
        $file = $query->fetch();
    } catch (Throwable $error) {
        log_app_error($error, 'download');
        http_response_code(503);
        exit('Download is temporarily unavailable.');
    }
    if (!$file || !is_file($file['result_path'])) {
        http_response_code(404);
        exit('File not found or expired.');
    }

    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $file['result_name']) ?: 'download';
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = match ($extension) {
        'zip' => 'application/zip',
        'txt' => 'text/plain; charset=UTF-8',
        'jpg', 'jpeg' => 'image/jpeg',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode((string) $file['result_name']));
    header('Content-Length: ' . (string) filesize($file['result_path']));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($file['result_path']);
    exit;
}

function api_delete(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    verify_csrf();
    $jobId = (string) ($_POST['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
        json_response(['success' => false, 'message' => 'The requested job was not found.'], 404);
    }
    try {
        $user = current_user();
        $query = db()->prepare(
            'SELECT j.id, j.file_id, j.result_path, f.stored_path
             FROM jobs j JOIN files f ON f.id = j.file_id
             WHERE j.id = :id AND ((j.user_id IS NOT NULL AND j.user_id = :user_id) OR
                  (j.user_id IS NULL AND j.session_hash = :session_hash))'
        );
        $query->execute(['id' => $jobId, 'user_id' => $user['id'] ?? null, 'session_hash' => session_owner_hash()]);
        $row = $query->fetch();
        if (!$row) {
            json_response(['success' => false, 'message' => 'The requested job was not found.'], 404);
        }
        foreach ([$row['result_path'], $row['stored_path']] as $path) {
            if (is_string($path) && is_file($path) && !unlink($path)) {
                json_response(['success' => false, 'message' => 'A temporary file could not be deleted. Try again or contact the server administrator.'], 500);
            }
        }
        $pdo = db();
        $pdo->beginTransaction();
        $delete = $pdo->prepare('DELETE FROM jobs WHERE id = :id');
        $delete->execute(['id' => $jobId]);
        $deleteFile = $pdo->prepare('DELETE FROM files WHERE id = :id');
        $deleteFile->execute(['id' => $row['file_id']]);
        $pdo->commit();
        json_response(['success' => true, 'message' => 'Temporary files deleted.']);
    } catch (Throwable $error) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_app_error($error, 'file-deletion');
        json_response(['success' => false, 'message' => 'Temporary files could not be deleted. Try again later.'], 500);
    }
}

function api_tools(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    json_response([
        'success' => true,
        'tools' => [
            ['slug' => 'pdf-to-jpg', 'name' => 'PDF to JPG', 'category' => 'Convert PDF', 'status' => 'ready'],
            ['slug' => 'jpg-to-pdf', 'name' => 'JPG to PDF', 'category' => 'Convert PDF', 'status' => 'ready'],
            ['slug' => 'pdf-to-word', 'name' => 'PDF to Word', 'category' => 'Convert PDF', 'status' => 'ready'],
            ['slug' => 'pdf-to-excel', 'name' => 'PDF to Excel', 'category' => 'Convert PDF', 'status' => 'ready'],
            ['slug' => 'pdf-to-ppt', 'name' => 'PDF to PowerPoint', 'category' => 'Convert PDF', 'status' => 'ready'],
            ['slug' => 'pdf-to-text', 'name' => 'PDF to Text', 'category' => 'Convert PDF', 'status' => 'ready'],
            ['slug' => 'merge-pdf', 'name' => 'Merge PDF', 'category' => 'Organize PDF', 'status' => 'ready'],
            ['slug' => 'split-pdf', 'name' => 'Split PDF', 'category' => 'Organize PDF', 'status' => 'ready'],
            ['slug' => 'compress-pdf', 'name' => 'Compress PDF', 'category' => 'Optimize PDF', 'status' => 'ready'],
            ['slug' => 'rotate-pdf', 'name' => 'Rotate PDF', 'category' => 'Organize PDF', 'status' => 'ready'],
            ['slug' => 'watermark-pdf', 'name' => 'Watermark PDF', 'category' => 'Edit PDF', 'status' => 'ready'],
            ['slug' => 'protect-pdf', 'name' => 'Protect PDF', 'category' => 'Security', 'status' => 'ready'],
            ['slug' => 'unlock-pdf', 'name' => 'Unlock PDF', 'category' => 'Security', 'status' => 'ready'],
        ],
    ]);
}
