<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdfforge-smoke-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700, true)) {
    throw new RuntimeException('Cannot create smoke-test directory.');
}

$chunk = static fn (string $type, string $data): string =>
    pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
$imagePath = $directory . DIRECTORY_SEPARATOR . 'pixel.png';
$png = "\x89PNG\r\n\x1a\n"
    . $chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0))
    . $chunk('IDAT', gzcompress("\0\xff\0\0"))
    . $chunk('IEND', '');
file_put_contents($imagePath, $png);

$service = new \PDFForge\Services\PdfToolsService();
$outputs = [];
try {
    $pdf = $service->process('jpg-to-pdf', [$imagePath], [], $directory, 'pixel');
    $outputs[] = $pdf['path'];
    if (file_get_contents($pdf['path'], false, null, 0, 5) !== '%PDF-') {
        throw new RuntimeException('JPG-to-PDF did not create a PDF.');
    }

    $merged = $service->process('merge-pdf', [$pdf['path'], $pdf['path']], [], $directory, 'merged');
    $outputs[] = $merged['path'];
    $singlePage = $service->process('split-pdf', [$merged['path']], ['pages' => '1'], $directory, 'single-page');
    $outputs[] = $singlePage['path'];
    $watermarked = $service->process('watermark-pdf', [$pdf['path']], ['text' => 'SMOKE TEST'], $directory, 'marked');
    $outputs[] = $watermarked['path'];
    $splitArchive = $service->process('split-pdf', [$merged['path']], [], $directory, 'pages');
    $outputs[] = $splitArchive['path'];

    $archive = new ZipArchive();
    if ($archive->open($splitArchive['path']) !== true || $archive->numFiles !== 2) {
        throw new RuntimeException('Splitting into individual pages did not create the expected ZIP.');
    }
    $archive->close();
    foreach ($outputs as $output) {
        if (!is_file($output) || filesize($output) === 0) {
            throw new RuntimeException('A PDF tool produced an empty result.');
        }
    }
    echo "Passed: image-to-PDF, merge, selected-page split, per-page ZIP split, and watermark.\n";
} finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    rmdir($directory);
}
