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
