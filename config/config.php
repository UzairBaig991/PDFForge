<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$serverName = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
$serverPort = (int) ($_SERVER['SERVER_PORT'] ?? ($scheme === 'https' ? 443 : 80));
$defaultPort = $scheme === 'https' ? 443 : 80;
$origin = $scheme . '://' . $serverName . ($serverPort === $defaultPort ? '' : ':' . $serverPort);
$defaultAppUrl = $origin . '/PDFForge';
$documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
$realProjectRoot = realpath($projectRoot);
if ($documentRoot !== false && $realProjectRoot !== false) {
    $documentPrefix = rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (str_starts_with(strtolower($realProjectRoot . DIRECTORY_SEPARATOR), strtolower($documentPrefix))) {
        $relativeRoot = substr($realProjectRoot, strlen($documentPrefix));
        $segments = array_filter(explode(DIRECTORY_SEPARATOR, $relativeRoot), static fn (string $segment): bool => $segment !== '');
        $defaultAppUrl = $origin . ($segments ? '/' . implode('/', array_map('rawurlencode', $segments)) : '');
    }
}
$envFile = $projectRoot . DIRECTORY_SEPARATOR . '.env';
$environment = [];

if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $environment[trim($key)] = trim(trim($value), "\"'");
    }
}

$readEnv = static fn (string $key, string $default = ''): string =>
    (string) ($environment[$key] ?? getenv($key) ?: $default);

return [
    'root' => $projectRoot,
    'app_name' => $readEnv('APP_NAME', 'PDFForge'),
    'app_url' => rtrim($readEnv('APP_URL', $defaultAppUrl), '/'),
    'environment' => $readEnv('APP_ENV', 'production'),
    'debug' => filter_var($readEnv('DEBUG_MODE', 'false'), FILTER_VALIDATE_BOOL),
    'database' => [
        'host' => $readEnv('DB_HOST', '127.0.0.1'),
        'port' => $readEnv('DB_PORT', '3306'),
        'name' => $readEnv('DB_NAME', 'pdfforge'),
        'user' => $readEnv('DB_USER', 'root'),
        'password' => $readEnv('DB_PASSWORD'),
    ],
    'max_upload_size' => max(1, (int) $readEnv('MAX_UPLOAD_SIZE', '52428800')),
    'retention_seconds' => max(60, (int) $readEnv('FILE_RETENTION_SECONDS', '3600')),
    'pdf_engine_path' => $readEnv('PDF_ENGINE_PATH'),
    'qpdf_path' => $readEnv('QPDF_PATH'),
    'ghostscript_path' => $readEnv('GHOSTSCRIPT_PATH'),
    'pdf_dpi' => max(72, min(300, (int) $readEnv('PDF_DPI', '150'))),
];
