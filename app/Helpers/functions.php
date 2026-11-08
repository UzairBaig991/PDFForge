<?php
declare(strict_types=1);

function app_config(?string $key = null): mixed
{
    global $config;
    return $key === null ? $config : ($config[$key] ?? null);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $settings = app_config('database');
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $settings['host'],
        $settings['port'],
        $settings['name']
    );
    $connection = new PDO($dsn, $settings['user'], $settings['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $connection;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        json_response(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
    }
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: ' . app_config('app_url') . '/login.php');
        exit;
    }
    return $user;
}

function session_owner_hash(): string
{
    return hash('sha256', session_id());
}

function ensure_storage_directories(): void
{
    foreach (['uploads', 'processed', 'temp', 'logs'] as $directory) {
        $path = APP_ROOT . '/storage/' . $directory;
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('Secure file storage is unavailable.');
        }
    }
}

function log_app_error(Throwable $error, string $context = 'application'): void
{
    ensure_storage_directories();
    $line = sprintf(
        "[%s] %s: %s\n",
        date(DATE_ATOM),
        preg_replace('/[^a-z0-9_-]/i', '', $context),
        str_replace(["\r", "\n"], ' ', $error->getMessage())
    );
    error_log($line, 3, APP_ROOT . '/storage/logs/application.log');
}

function render_header(string $title, string $description = ''): void
{
    $pageTitle = $title === 'PDFForge' ? $title : $title . ' | PDFForge';
    require APP_ROOT . '/includes/header.php';
}

function render_footer(): void
{
    require APP_ROOT . '/includes/footer.php';
}

function human_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    $units = ['KB', 'MB', 'GB'];
    $value = $bytes / 1024;
    foreach ($units as $unit) {
        if ($value < 1024 || $unit === 'GB') {
            return number_format($value, 1) . ' ' . $unit;
        }
        $value /= 1024;
    }
    return number_format($value, 1) . ' GB';
}
