<?php
declare(strict_types=1);

namespace {
$config = require __DIR__ . '/config/config.php';
define('APP_ROOT', $config['root']);

if (is_file(APP_ROOT . '/vendor/autoload.php')) {
    require APP_ROOT . '/vendor/autoload.php';
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_name('pdfforge_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}





if (empty($config['debug'])) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
ini_set('log_errors', '1');
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
    render_header_template($pageTitle, $description);
}

function render_footer(): void
{
    render_footer_template();
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


function render_header_template(string $pageTitle, string $description): void
{
$description = $description !== '' ? $description : 'Simple, secure tools to convert and manage your PDF files.';
$baseUrl = app_config('app_url');
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <link rel="canonical" href="<?= e($baseUrl . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="#5b4bff">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/assets/css/main.css">
    <script>window.PDFFORGE = <?= json_encode([
        'baseUrl' => $baseUrl,
        'csrfToken' => csrf_token(),
        'maxUploadSize' => app_config('max_upload_size'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script defer src="<?= e($baseUrl) ?>/assets/js/app.js"></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header" id="site-header">
    <nav class="navbar container" aria-label="Main navigation">
        <a class="brand" href="<?= e($baseUrl) ?>/index.php" aria-label="PDFForge home">
            <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M7 3h12l7 7v19H7z"/><path d="M19 3v8h7M11 18h11M11 23h8"/></svg></span>
            <span>PDF<span class="brand-accent">Forge</span></span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-menu" aria-label="Open menu"><span></span><span></span><span></span></button>
        <div class="nav-menu" id="primary-menu">
            <div class="nav-links">
                <details class="nav-dropdown"><summary>Tools <span aria-hidden="true">⌄</span></summary><div class="nav-popover"><strong>Explore PDF tools</strong><a href="<?= e($baseUrl) ?>/tools/tools.php">All tools</a><a href="<?= e($baseUrl) ?>/tools/pdf-to-jpg.php">PDF to JPG <span class="menu-ready">Ready</span></a><a href="<?= e($baseUrl) ?>/tools/merge-pdf.php">Merge PDF <span class="menu-ready">Ready</span></a></div></details>
                <a href="<?= e($baseUrl) ?>/index.php#features">Features</a>
                <a href="<?= e($baseUrl) ?>/pricing.php">Pricing</a>
                <a href="<?= e($baseUrl) ?>/about.php">About</a>
                <a href="<?= e($baseUrl) ?>/contact.php">Contact</a>
            </div>
            <div class="nav-actions">
                <button class="theme-toggle" type="button" aria-label="Toggle dark mode"><span aria-hidden="true">◐</span></button>
                <?php if (current_user()): ?>
                    <a class="nav-login" href="<?= e($baseUrl) ?>/dashboard.php">Dashboard</a>
                <?php else: ?>
                    <a class="nav-login" href="<?= e($baseUrl) ?>/login.php">Log in</a>
                <?php endif; ?>
                <a class="button button-small" href="<?= e($baseUrl) ?>/register.php">Get started</a>
            </div>
        </div>
    </nav>
</header>
<?php }

function render_footer_template(): void
{
$baseUrl = app_config('app_url'); ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a class="brand" href="<?= e($baseUrl) ?>/index.php"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M7 3h12l7 7v19H7z"/><path d="M19 3v8h7M11 18h11M11 23h8"/></svg></span><span>PDF<span class="brand-accent">Forge</span></span></a>
            <p>Thoughtful tools for your everyday documents.</p>
        </div>
        <div><h2>Tools</h2><a href="<?= e($baseUrl) ?>/tools/tools.php">All PDF tools</a><a href="<?= e($baseUrl) ?>/tools/pdf-to-jpg.php">PDF to JPG</a><a href="<?= e($baseUrl) ?>/tools/merge-pdf.php">Merge PDF</a></div>
        <div><h2>Company</h2><a href="<?= e($baseUrl) ?>/about.php">About</a><a href="<?= e($baseUrl) ?>/contact.php">Contact</a><a href="<?= e($baseUrl) ?>/pricing.php">Pricing</a></div>
        <div><h2>Resources</h2><a href="<?= e($baseUrl) ?>/faq.php">FAQ</a><a href="<?= e($baseUrl) ?>/privacy.php">Privacy</a><a href="<?= e($baseUrl) ?>/terms.php">Terms</a></div>
    </div>
    <div class="container footer-bottom"><span>© <?= date('Y') ?> PDFForge</span><span>Files expire on schedule and are removed by the cleanup task.</span></div>
</footer>
</body>
</html>
<?php }

function render_upload_form(?string $uploadTool = null): void
{
    $uploadTool = $uploadTool ?? 'pdf-to-jpg';
$multiUpload = in_array($uploadTool ?? '', ['jpg-to-pdf', 'merge-pdf'], true);
$imageUpload = ($uploadTool ?? '') === 'jpg-to-pdf';
$acceptedTypes = $imageUpload ? 'image/jpeg,image/png,.jpg,.jpeg,.png' : 'application/pdf,.pdf';
$uploadTitleText = $imageUpload ? 'Drag & drop your images here' : 'Drag & drop your PDF here';
$selectionLabel = $imageUpload ? 'image files' : ($multiUpload ? 'PDF files' : 'a PDF file');
?>
<form class="upload-form" data-upload-form data-tool="<?= e($uploadTool ?? 'pdf-to-jpg') ?>" data-image-upload="<?= $imageUpload ? 'true' : 'false' ?>" action="<?= e(app_config('app_url')) ?>/api/upload.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="tool" value="<?= e($uploadTool ?? 'pdf-to-jpg') ?>">
    <input class="file-input" type="file" name="files[]" accept="<?= e($acceptedTypes) ?>" <?= $multiUpload ? 'multiple' : '' ?> data-file-input aria-label="Choose <?= e($selectionLabel) ?>">
    <div class="drop-zone" data-drop-zone tabindex="0" role="button" aria-label="Choose or drop <?= e($selectionLabel) ?>">
        <span class="drop-icon" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M12 5h16l9 9v29H12z"/><path d="M28 5v10h9M17 25h15M17 31h15M17 37h10"/></svg></span>
        <strong data-upload-title><?= e($uploadTitleText) ?></strong>
        <span class="upload-or">or</span>
        <button class="button button-upload" type="button" data-select-file><?= $imageUpload ? 'Select image files' : ($multiUpload ? 'Select files' : 'Select PDF file') ?></button>
        <span class="upload-limit"><?= $imageUpload ? 'JPG or PNG' : 'PDF' ?><?= $multiUpload ? ' · up to 20 files' : '' ?> · combined size up to <?= e(human_size((int) app_config('max_upload_size'))) ?></span>
    </div>
    <div class="upload-file" data-upload-file hidden><span class="file-badge">PDF</span><span class="file-name" data-file-name></span><button type="button" class="icon-button" data-remove-file aria-label="Remove selected file">×</button></div>
    <div class="upload-status" data-upload-status role="status" aria-live="polite" hidden>
        <div class="status-head"><span data-status-text>Uploading…</span><button type="button" class="text-button" data-cancel hidden>Cancel</button></div>
        <progress class="upload-progress" data-progress max="100" value="0" aria-label="Upload progress"></progress>
        <div class="result-actions" data-result hidden></div>
        <button class="button button-small upload-retry" type="button" data-retry hidden>Try again</button>
    </div>
    <?php if (($uploadTool ?? '') === 'split-pdf'): ?>
        <label class="tool-option">Pages to extract <input type="text" name="pages" placeholder="Leave blank to split every page, or enter 1-3,5"></label>
    <?php elseif (($uploadTool ?? '') === 'rotate-pdf'): ?>
        <label class="tool-option">Rotation <select name="degrees"><option value="90">90° clockwise</option><option value="180">180°</option><option value="270">270° clockwise</option></select></label>
        <label class="tool-option">Pages <input type="text" name="pages" placeholder="Leave blank for all, or enter 1-3,5"></label>
    <?php elseif (($uploadTool ?? '') === 'watermark-pdf'): ?>
        <label class="tool-option">Watermark text <input type="text" name="text" maxlength="100" value="CONFIDENTIAL" required></label>
    <?php elseif (($uploadTool ?? '') === 'compress-pdf'): ?>
        <label class="tool-option">Compression quality <select name="quality"><option value="screen">Smallest file</option><option value="ebook" selected>Balanced</option><option value="printer">Higher quality</option></select></label>
    <?php elseif (in_array($uploadTool ?? '', ['protect-pdf', 'unlock-pdf'], true)): ?>
        <label class="tool-option"><?= ($uploadTool ?? '') === 'protect-pdf' ? 'New PDF password' : 'Current PDF password' ?><input type="password" name="password" minlength="<?= ($uploadTool ?? '') === 'protect-pdf' ? '6' : '1' ?>" maxlength="128" autocomplete="new-password" required></label>
    <?php endif; ?>
</form>
<?php }

function render_error_page(int $status): void
{
    http_response_code($status);
    $details = match ($status) {
        403 => ['Access denied', '403 - ACCESS DENIED', 'You do not have access.', 'This area is restricted to authorized accounts.'],
        500 => ['Something went wrong', '500 - SERVER ERROR', 'Something went wrong.', 'Please try again. The server has not exposed internal diagnostic details.'],
        default => ['Page not found', '404 - NOT FOUND', 'This page is not here.', 'The link may be outdated, or the page may have moved.'],
    };
    render_header($details[0]);
    ?>
    <main id="main" class="page-main"><section class="auth-card error-card"><span class="eyebrow"><?= e($details[1]) ?></span><h1><?= e($details[2]) ?></h1><p><?= e($details[3]) ?></p><a class="button button-outline" href="<?= e(app_config('app_url')) ?><?= $status === 403 ? '/dashboard.php' : '/index.php' ?>"><?= $status === 403 ? 'Return to dashboard' : 'Back to home' ?></a></section></main>
    <?php
    render_footer();
}
}

namespace PDFForge\Services {
use RuntimeException;
use setasign\Fpdi\Fpdi;
use ZipArchive;

final class PdfEngine
{
    public function executable(string $program): string
    {
        $engineName = match ($program) {
            'qpdf' => 'QPDF',
            'gs' => 'Ghostscript',
            default => 'PDF processing engine',
        };
        $setting = trim((string) app_config(match ($program) {
            'qpdf' => 'qpdf_path',
            'gs' => 'ghostscript_path',
            default => 'pdf_engine_path',
        }));
        if ($setting === '') {
            throw new RuntimeException($engineName . ' is not configured.');
        }

        $name = match ($program) {
            'gs' => PHP_OS_FAMILY === 'Windows' ? 'gswin64c.exe' : 'gs',
            default => $program . (PHP_OS_FAMILY === 'Windows' ? '.exe' : ''),
        };
        if (is_file($setting)) {
            $candidate = strcasecmp(basename($setting), $name) === 0
                ? $setting
                : dirname($setting) . DIRECTORY_SEPARATOR . $name;
        } else {
            $candidate = rtrim($setting, DIRECTORY_SEPARATOR . '/\\') . DIRECTORY_SEPARATOR . $name;
        }
        if (!is_file($candidate) || !is_executable($candidate)) {
            throw new RuntimeException($engineName . ' is not configured.');
        }
        return $candidate;
    }

    public function run(array $command, string $outputDirectory): int
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('The PDF processing engine cannot be started on this server.');
        }

        $stdout = fopen($outputDirectory . DIRECTORY_SEPARATOR . 'engine.stdout', 'wb');
        $stderr = fopen($outputDirectory . DIRECTORY_SEPARATOR . 'engine.stderr', 'wb');
        if ($stdout === false || $stderr === false) {
            if (is_resource($stdout)) {
                fclose($stdout);
            }
            if (is_resource($stderr)) {
                fclose($stderr);
            }
            throw new RuntimeException('Temporary processing storage is unavailable.');
        }

        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => $stdout,
            2 => $stderr,
        ], $pipes, APP_ROOT);
        fclose($stdout);
        fclose($stderr);
        if (!is_resource($process)) {
            throw new RuntimeException('The PDF processing engine could not be started.');
        }

        fclose($pipes[0]);
        return proc_close($process);
    }
}

final class PdfToJpgService
{
    public function convert(string $inputPath, string $outputDirectory): array
    {
        if (!is_file($inputPath) || !is_dir($outputDirectory)) {
            throw new RuntimeException('The PDF file is no longer available.');
        }

        $prefix = $outputDirectory . DIRECTORY_SEPARATOR . 'page';
        $command = [
            (new PdfEngine())->executable('pdftoppm'),
            '-jpeg',
            '-r',
            (string) app_config('pdf_dpi'),
            '-progress',
            $inputPath,
            $prefix,
        ];
        $exitCode = (new PdfEngine())->run($command, $outputDirectory);

        $pages = glob($prefix . '-*.jpg') ?: [];
        natsort($pages);
        $pages = array_values($pages);
        if ($exitCode !== 0 || count($pages) === 0) {
            throw new RuntimeException('PDF conversion failed. Check that the PDF is valid and Poppler is installed.');
        }

        return $pages;
    }
}

final class PdfTextService
{
    public function extract(string $inputPath, string $outputDirectory): string
    {
        if (!is_file($inputPath) || !is_dir($outputDirectory)) {
            throw new RuntimeException('The PDF file is no longer available.');
        }

        $outputPath = $outputDirectory . DIRECTORY_SEPARATOR . 'document.txt';
        $command = [
            (new PdfEngine())->executable('pdftotext'),
            '-layout',
            '-enc',
            'UTF-8',
            $inputPath,
            $outputPath,
        ];
        $exitCode = (new PdfEngine())->run($command, $outputDirectory);
        if ($exitCode !== 0 || !is_file($outputPath)) {
            throw new RuntimeException('PDF text extraction failed. Check that the PDF is valid and Poppler is installed.');
        }
        $text = file_get_contents($outputPath);
        if ($text === false) {
            throw new RuntimeException('The extracted PDF text could not be read.');
        }
        if (trim($text) === '') {
            throw new RuntimeException('No selectable text was found. Scanned PDFs require OCR.');
        }
        return $outputPath;
    }
}

final class PdfToolException extends RuntimeException
{
}

final class PdfToolsService
{
    public function process(string $tool, array $inputs, array $options, string $directory, string $baseName): array
    {
        return match ($tool) {
            'pdf-to-jpg' => $this->renderJpg($inputs[0], $directory, $baseName),
            'pdf-to-text' => $this->extractText($inputs[0], $directory, $baseName),
            'jpg-to-pdf' => $this->imagesToPdf($inputs, $directory, $baseName),
            'merge-pdf' => $this->merge($inputs, $directory, $baseName),
            'split-pdf' => $this->split($inputs[0], $options, $directory, $baseName),
            'rotate-pdf' => $this->rotate($inputs[0], $options, $directory, $baseName),
            'watermark-pdf' => $this->watermark($inputs[0], $options, $directory, $baseName),
            'compress-pdf' => $this->compress($inputs[0], $options, $directory, $baseName),
            'protect-pdf' => $this->protect($inputs[0], $options, $directory, $baseName),
            'unlock-pdf' => $this->unlock($inputs[0], $options, $directory, $baseName),
            'pdf-to-word' => $this->toWord($inputs[0], $directory, $baseName),
            'pdf-to-excel' => $this->toExcel($inputs[0], $directory, $baseName),
            'pdf-to-ppt' => $this->toPowerPoint($inputs[0], $directory, $baseName),
            default => throw new PdfToolException('That PDF tool is not available.'),
        };
    }

    private function renderJpg(string $input, string $directory, string $baseName): array
    {
        $pages = (new PdfToJpgService())->convert($input, $directory);
        if (count($pages) === 1) {
            return $this->moveResult($pages[0], $directory, $baseName . '.jpg', ['page_count' => 1]);
        }
        return $this->zipResult($pages, $directory, $baseName . '-pages.zip', 'page-');
    }

    private function extractText(string $input, string $directory, string $baseName): array
    {
        $path = (new PdfTextService())->extract($input, $directory);
        return $this->moveResult($path, $directory, $baseName . '-text.txt');
    }

    private function imagesToPdf(array $inputs, string $directory, string $baseName): array
    {
        $pdf = new Fpdi();
        foreach ($inputs as $input) {
            $size = @getimagesize($input);
            if ($size === false || !in_array($size['mime'] ?? '', ['image/jpeg', 'image/png'], true)) {
                throw new PdfToolException('Choose valid JPG or PNG image files.');
            }
            $width = (float) $size[0];
            $height = (float) $size[1];
            $orientation = $width > $height ? 'L' : 'P';
            $pdf->AddPage($orientation, [$width * 0.264583, $height * 0.264583]);
            $pdf->Image($input, 0, 0, $width * 0.264583, $height * 0.264583);
        }
        return $this->writeFpdi($pdf, $directory, $baseName . '.pdf');
    }

    private function merge(array $inputs, string $directory, string $baseName): array
    {
        if (count($inputs) < 2) {
            throw new PdfToolException('Select at least two PDF files to merge.');
        }
        $pdf = new Fpdi();
        foreach ($inputs as $input) {
            $this->appendPages($pdf, $input);
        }
        return $this->writeFpdi($pdf, $directory, $baseName . '-merged.pdf');
    }

    private function split(string $input, array $options, string $directory, string $baseName): array
    {
        $ranges = $this->requestedRanges($this->optionString($options, 'pages'), $input);
        $parts = [];
        foreach ($ranges as $index => $range) {
            $pdf = new Fpdi();
            foreach ($range as $pageNumber) {
                $this->appendPage($pdf, $input, $pageNumber);
            }
            $partPath = $directory . DIRECTORY_SEPARATOR . 'part-' . ($index + 1) . '.pdf';
            $pdf->Output('F', $partPath);
            $parts[] = $partPath;
        }
        if (count($parts) === 1) {
            return $this->moveResult($parts[0], $directory, $baseName . '-split.pdf');
        }
        return $this->zipResult($parts, $directory, $baseName . '-split.zip', 'part-', 'pdf');
    }

    private function rotate(string $input, array $options, string $directory, string $baseName): array
    {
        $degrees = (int) $this->optionString($options, 'degrees', '90');
        if (!in_array($degrees, [90, 180, 270], true)) {
            throw new PdfToolException('Choose a rotation of 90, 180, or 270 degrees.');
        }
        $pageCount = $this->pageCount($input);
        $pages = $this->parsePages($this->optionString($options, 'pages'), $pageCount);
        $output = $directory . DIRECTORY_SEPARATOR . 'rotated.pdf';
        $engine = new PdfEngine();
        $exitCode = $engine->run([
            $engine->executable('qpdf'),
            '--rotate=+' . $degrees . ':' . implode(',', $pages),
            '--',
            $input,
            $output,
        ], $directory);
        if ($exitCode !== 0 || !is_file($output)) {
            throw new PdfToolException('PDF rotation failed. Check that qpdf is installed and the PDF is valid.');
        }
        return $this->moveResult($output, $directory, $baseName . '-rotated.pdf');
    }

    private function watermark(string $input, array $options, string $directory, string $baseName): array
    {
        $text = trim($this->optionString($options, 'text'));
        if ($text === '' || mb_strlen($text) > 100 || preg_match('/[\x00-\x1F\x7F]/', $text)) {
            throw new PdfToolException('Enter watermark text of 1 to 100 characters.');
        }
        $pdf = new Fpdi();
        $count = $pdf->setSourceFile($input);
        for ($page = 1; $page <= $count; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
            $pdf->SetFont('Arial', 'B', 26);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->Text(12, $size['height'] / 2, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text) ?: $text);
        }
        return $this->writeFpdi($pdf, $directory, $baseName . '-watermarked.pdf');
    }

    private function compress(string $input, array $options, string $directory, string $baseName): array
    {
        $preset = $this->optionString($options, 'quality', 'ebook');
        if (!in_array($preset, ['screen', 'ebook', 'printer'], true)) {
            throw new PdfToolException('Choose a valid compression quality.');
        }
        $output = $directory . DIRECTORY_SEPARATOR . 'compressed.pdf';
        $engine = new PdfEngine();
        $exitCode = $engine->run([
            $engine->executable('gs'),
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.5',
            '-dPDFSETTINGS=/' . $preset,
            '-dNOPAUSE',
            '-dBATCH',
            '-dSAFER',
            '-sOutputFile=' . $output,
            $input,
        ], $directory);
        if ($exitCode !== 0 || !is_file($output)) {
            throw new PdfToolException('PDF compression failed. Check that Ghostscript is installed and the PDF is valid.');
        }
        return $this->moveResult($output, $directory, $baseName . '-compressed.pdf');
    }

    private function protect(string $input, array $options, string $directory, string $baseName): array
    {
        $password = $this->optionString($options, 'password');
        if (strlen($password) < 6 || strlen($password) > 128 || preg_match('/[\x00-\x1F\x7F]/', $password)) {
            throw new PdfToolException('Choose a password between 6 and 128 characters.');
        }
        $output = $directory . DIRECTORY_SEPARATOR . 'protected.pdf';
        $engine = new PdfEngine();
        $ownerPassword = bin2hex(random_bytes(24));
        $exitCode = $engine->run([
            $engine->executable('qpdf'), '--encrypt', $password, $ownerPassword, '256', '--', $input, $output,
        ], $directory);
        if ($exitCode !== 0 || !is_file($output)) {
            throw new PdfToolException('PDF protection failed. Check that qpdf is installed and the PDF is valid.');
        }
        return $this->moveResult($output, $directory, $baseName . '-protected.pdf');
    }

    private function unlock(string $input, array $options, string $directory, string $baseName): array
    {
        $password = $this->optionString($options, 'password');
        if ($password === '' || strlen($password) > 128 || preg_match('/[\x00-\x1F\x7F]/', $password)) {
            throw new PdfToolException('Enter the PDF password to unlock this document.');
        }
        $output = $directory . DIRECTORY_SEPARATOR . 'unlocked.pdf';
        $engine = new PdfEngine();
        $exitCode = $engine->run([
            $engine->executable('qpdf'), '--password=' . $password, '--decrypt', $input, $output,
        ], $directory);
        if ($exitCode !== 0 || !is_file($output)) {
            throw new PdfToolException('Could not unlock the PDF. Check the password and your permission to modify this document.');
        }
        return $this->moveResult($output, $directory, $baseName . '-unlocked.pdf');
    }

    private function toWord(string $input, string $directory, string $baseName): array
    {
        $text = $this->text($input, $directory);
        $paragraphs = '';
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $paragraphs .= '<w:p><w:r><w:t xml:space="preserve">' . $this->xml($line) . '</w:t></w:r></w:p>';
            }
        }
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            . $paragraphs . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>'
            . '</w:body></w:document>';
        $output = $directory . DIRECTORY_SEPARATOR . 'document.docx';
        $this->package($output, [
            '[Content_Types].xml' => $this->wordContentTypes(),
            '_rels/.rels' => $this->rootRelationship('word/document.xml'),
            'word/document.xml' => $document,
        ]);
        return $this->moveResult($output, $directory, $baseName . '.docx');
    }

    private function toExcel(string $input, string $directory, string $baseName): array
    {
        $text = $this->text($input, $directory);
        $rows = '';
        $rowNumber = 0;
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $cells = preg_split('/\s{2,}/u', $line) ?: [$line];
            $rows .= '<row r="' . (++$rowNumber) . '">';
            foreach (array_values($cells) as $column => $cell) {
                $reference = $this->columnName($column + 1) . $rowNumber;
                $rows .= '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">'
                    . $this->xml(trim($cell)) . '</t></is></c>';
            }
            $rows .= '</row>';
        }
        $output = $directory . DIRECTORY_SEPARATOR . 'document.xlsx';
        $this->package($output, [
            '[Content_Types].xml' => $this->excelContentTypes(),
            '_rels/.rels' => $this->rootRelationship('xl/workbook.xml'),
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Extracted text" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $rows . '</sheetData></worksheet>',
        ]);
        return $this->moveResult($output, $directory, $baseName . '.xlsx');
    }

    private function toPowerPoint(string $input, string $directory, string $baseName): array
    {
        $text = $this->text($input, $directory);
        $pages = preg_split('/\f/u', $text) ?: [$text];
        while (count($pages) > 1 && trim((string) end($pages)) === '') {
            array_pop($pages);
        }
        if (!$pages) {
            $pages = ['No selectable text was found on this PDF page.'];
        }
        $content = [
            '[Content_Types].xml' => $this->powerPointContentTypes(count($pages)),
            '_rels/.rels' => $this->rootRelationship('ppt/presentation.xml'),
            'ppt/presentation.xml' => $this->presentationXml(count($pages)),
            'ppt/_rels/presentation.xml.rels' => $this->presentationRelationships(count($pages)),
            'ppt/slideMasters/slideMaster1.xml' => $this->slideMasterXml(),
            'ppt/slideMasters/_rels/slideMaster1.xml.rels' => $this->slideMasterRelationships(),
            'ppt/slideLayouts/slideLayout1.xml' => $this->slideLayoutXml(),
            'ppt/slideLayouts/_rels/slideLayout1.xml.rels' => $this->slideLayoutRelationships(),
            'ppt/theme/theme1.xml' => $this->themeXml(),
        ];
        foreach ($pages as $index => $page) {
            $slideNumber = $index + 1;
            $content['ppt/slides/slide' . $slideNumber . '.xml'] = $this->slideXml($page, $slideNumber);
            $content['ppt/slides/_rels/slide' . $slideNumber . '.xml.rels'] =
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/></Relationships>';
        }
        $output = $directory . DIRECTORY_SEPARATOR . 'document.pptx';
        $this->package($output, $content);
        return $this->moveResult($output, $directory, $baseName . '.pptx', ['slide_count' => count($pages)]);
    }

    private function text(string $input, string $directory): string
    {
        $path = (new PdfTextService())->extract($input, $directory);
        $text = file_get_contents($path);
        if ($text === false) {
            throw new PdfToolException('The PDF text could not be read.');
        }
        if (trim($text) === '') {
            throw new PdfToolException('No selectable text was found. Scanned PDFs require OCR.');
        }
        return $text;
    }

    private function requestedRanges(string $value, string $input): array
    {
        $count = $this->pageCount($input);
        $selected = $this->parsePages($value, $count);
        if (trim($value) === '') {
            return array_map(static fn (int $page): array => [$page], $selected);
        }
        return [$selected];
    }

    private function parsePages(string $value, int $count): array
    {
        $value = trim($value);
        if ($value === '') {
            return range(1, $count);
        }
        if (strlen($value) > 500 || !preg_match('/^\d+(?:-\d+)?(?:,\d+(?:-\d+)?)*$/', $value)) {
            throw new PdfToolException('Enter page numbers or ranges such as 1-3,5.');
        }
        $pages = [];
        foreach (explode(',', $value) as $part) {
            $bounds = array_map('intval', explode('-', $part));
            $start = $bounds[0];
            $end = $bounds[1] ?? $start;
            if ($start < 1 || $end < $start || $end > $count) {
                throw new PdfToolException('A selected page is outside this PDF.');
            }
            for ($page = $start; $page <= $end; $page++) {
                $pages[$page] = $page;
            }
        }
        return array_values($pages);
    }

    private function pageCount(string $input): int
    {
        $pdf = new Fpdi();
        return $pdf->setSourceFile($input);
    }

    private function appendPages(Fpdi $pdf, string $input): void
    {
        $count = $pdf->setSourceFile($input);
        for ($page = 1; $page <= $count; $page++) {
            $this->appendPage($pdf, $input, $page);
        }
    }

    private function appendPage(Fpdi $pdf, string $input, int $page): void
    {
        $pdf->setSourceFile($input);
        $template = $pdf->importPage($page);
        $size = $pdf->getTemplateSize($template);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($template);
    }

    private function writeFpdi(Fpdi $pdf, string $directory, string $name): array
    {
        $path = $directory . DIRECTORY_SEPARATOR . 'result.pdf';
        $pdf->Output('F', $path);
        return $this->moveResult($path, $directory, $name);
    }

    private function moveResult(string $source, string $directory, string $name, array $metadata = []): array
    {
        $path = $directory . DIRECTORY_SEPARATOR . 'result-' . bin2hex(random_bytes(4)) . '.' . pathinfo($name, PATHINFO_EXTENSION);
        if (!rename($source, $path)) {
            throw new PdfToolException('The converted file could not be saved.');
        }
        return ['path' => $path, 'name' => $name] + $metadata;
    }

    private function zipResult(array $files, string $directory, string $name, string $prefix, string $extension = 'jpg'): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new PdfToolException('ZIP support is not configured.');
        }
        $path = $directory . DIRECTORY_SEPARATOR . 'result-' . bin2hex(random_bytes(4)) . '.zip';
        $archive = new ZipArchive();
        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new PdfToolException('The converted files could not be packaged for download.');
        }
        foreach (array_values($files) as $index => $file) {
            if (!$archive->addFile($file, $prefix . ($index + 1) . '.' . $extension)) {
                $archive->close();
                throw new PdfToolException('A converted file could not be added to the download.');
            }
        }
        $archive->close();
        $metadata = $extension === 'jpg' ? ['page_count' => count($files)] : [];
        return ['path' => $path, 'name' => $name] + $metadata;
    }

    private function package(string $path, array $files): void
    {
        $archive = new ZipArchive();
        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new PdfToolException('The office document could not be packaged.');
        }
        foreach ($files as $name => $contents) {
            if (!$archive->addFromString($name, $contents)) {
                $archive->close();
                throw new PdfToolException('The office document could not be packaged.');
            }
        }
        $archive->close();
    }

    private function rootRelationship(string $target): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="' . $target . '"/></Relationships>';
    }

    private function xml(string $value): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw new PdfToolException('The PDF text is not valid UTF-8.');
        }
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function optionString(array $options, string $key, string $default = ''): string
    {
        $value = $options[$key] ?? $default;
        if (!is_string($value)) {
            throw new PdfToolException('Invalid tool options.');
        }
        return $value;
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }
        return $name;
    }

    private function wordContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>';
    }

    private function excelContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    }

    private function powerPointContentTypes(int $count): string
    {
        $overrides = '<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/><Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/><Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/><Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>';
        for ($slide = 1; $slide <= $count; $slide++) {
            $overrides .= '<Override PartName="/ppt/slides/slide' . $slide . '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>' . $overrides . '</Types>';
    }

    private function presentationXml(int $count): string
    {
        $slides = '';
        for ($slide = 1; $slide <= $count; $slide++) {
            $slides .= '<p:sldId id="' . (255 + $slide) . '" r:id="rId' . ($slide + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst><p:sldIdLst>' . $slides . '</p:sldIdLst><p:sldSz cx="9144000" cy="5143500" type="wide"/><p:notesSz cx="6858000" cy="9144000"/></p:presentation>';
    }

    private function presentationRelationships(int $count): string
    {
        $relationships = '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>';
        for ($slide = 1; $slide <= $count; $slide++) {
            $relationships .= '<Relationship Id="rId' . ($slide + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide' . $slide . '.xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relationships . '</Relationships>';
    }

    private function slideXml(string $text, int $slide): string
    {
        $paragraphs = '';
        foreach (preg_split('/\R/u', trim($text)) ?: [] as $line) {
            if (trim($line) !== '') {
                $paragraphs .= '<a:p><a:r><a:rPr lang="en-US" sz="1800"/><a:t>' . $this->xml(trim($line)) . '</a:t></a:r><a:endParaRPr lang="en-US"/></a:p>';
            }
        }
        if ($paragraphs === '') {
            $paragraphs = '<a:p><a:r><a:rPr lang="en-US" sz="1800"/><a:t>No selectable text found on this page.</a:t></a:r></a:p>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr><p:sp><p:nvSpPr><p:cNvPr id="2" name="Slide ' . $slide . ' text"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="457200" y="457200"/><a:ext cx="8229600" cy="4236720"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr><p:txBody><a:bodyPr wrap="square"/><a:lstStyle/>' . $paragraphs . '</p:txBody></p:sp></p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>';
    }

    private function slideMasterXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld><p:clrMap accent1="4F46E5" accent2="7C3AED" accent3="06B6D4" accent4="22C55E" accent5="F59E0B" accent6="EF4444" bg1="lt1" bg2="lt2" tx1="dk1" tx2="dk2" folHlink="hlink" hlink="folHlink"/><p:sldLayoutIdLst><p:sldLayoutId id="1" r:id="rId1"/></p:sldLayoutIdLst><p:txStyles><p:titleStyle/><p:bodyStyle/><p:otherStyle/></p:txStyles></p:sldMaster>';
    }

    private function slideMasterRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/></Relationships>';
    }

    private function slideLayoutXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank" preserve="1"><p:cSld name="Blank"><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sldLayout>';
    }

    private function slideLayoutRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/></Relationships>';
    }

    private function themeXml(): string
    {
        $fill = '<a:solidFill><a:schemeClr val="phClr"/></a:solidFill>';
        $line = '<a:ln w="6350">' . $fill . '<a:prstDash val="solid"/></a:ln>';
        $effect = '<a:effectStyle><a:effectLst/></a:effectStyle>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="PDFForge"><a:themeElements>'
            . '<a:clrScheme name="PDFForge"><a:dk1><a:srgbClr val="000000"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1>'
            . '<a:dk2><a:srgbClr val="333333"/></a:dk2><a:lt2><a:srgbClr val="F2F2F2"/></a:lt2>'
            . '<a:accent1><a:srgbClr val="4F46E5"/></a:accent1><a:accent2><a:srgbClr val="7C3AED"/></a:accent2>'
            . '<a:accent3><a:srgbClr val="06B6D4"/></a:accent3><a:accent4><a:srgbClr val="22C55E"/></a:accent4>'
            . '<a:accent5><a:srgbClr val="F59E0B"/></a:accent5><a:accent6><a:srgbClr val="EF4444"/></a:accent6>'
            . '<a:hlink><a:srgbClr val="0000FF"/></a:hlink><a:folHlink><a:srgbClr val="800080"/></a:folHlink></a:clrScheme>'
            . '<a:fontScheme name="PDFForge"><a:majorFont><a:latin typeface="Arial"/></a:majorFont>'
            . '<a:minorFont><a:latin typeface="Arial"/></a:minorFont></a:fontScheme>'
            . '<a:fmtScheme name="PDFForge"><a:fillStyleLst>' . $fill . $fill . $fill . '</a:fillStyleLst>'
            . '<a:lnStyleLst>' . $line . $line . $line . '</a:lnStyleLst>'
            . '<a:effectStyleLst>' . $effect . $effect . $effect . '</a:effectStyleLst>'
            . '<a:bgFillStyleLst>' . $fill . $fill . $fill . '</a:bgFillStyleLst></a:fmtScheme>'
            . '</a:themeElements></a:theme>';
    }
}
}
