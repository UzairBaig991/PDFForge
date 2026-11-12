<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/app.php';

try {
    $select = db()->query(
        'SELECT f.id, f.stored_path, j.result_path
         FROM files f LEFT JOIN jobs j ON j.file_id = f.id
         WHERE f.expires_at <= NOW()'
    );
    $deleteFile = db()->prepare('DELETE FROM files WHERE id = :id AND expires_at <= NOW()');
    $removed = 0;
    foreach ($select->fetchAll() as $row) {
        $paths = [];
        foreach ([$row['stored_path'], $row['result_path']] as $path) {
            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }
        $allRemoved = true;
        foreach (array_unique($paths) as $path) {
            if (is_file($path)) {
                if (!unlink($path)) {
                    $allRemoved = false;
                } else {
                    $removed++;
                }
            }
        }
        if ($allRemoved) {
            $deleteFile->execute(['id' => $row['id']]);
        } else {
            log_app_error(new RuntimeException('An expired file could not be removed from storage.'), 'cleanup');
        }
    }

    $temporaryRoot = APP_ROOT . '/storage/temp';
    $cutoff = time() - (int) app_config('retention_seconds');
    foreach (glob($temporaryRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $directory) {
        if (filemtime($directory) !== false && filemtime($directory) < $cutoff) {
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            rmdir($directory);
        }
    }
    fwrite(STDOUT, "Cleanup complete. Removed {$removed} stored file(s).\n");
} catch (Throwable $exception) {
    log_app_error($exception, 'cleanup');
    fwrite(STDERR, "Cleanup failed. Check storage/logs/application.log.\n");
    exit(1);
}
