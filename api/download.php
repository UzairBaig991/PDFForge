<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

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
