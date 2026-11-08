<?php
declare(strict_types=1);

namespace PDFForge\Services;

use RuntimeException;

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
