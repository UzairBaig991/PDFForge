<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

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
