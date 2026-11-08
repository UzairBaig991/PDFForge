<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

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
