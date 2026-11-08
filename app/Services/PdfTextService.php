<?php
declare(strict_types=1);

namespace PDFForge\Services;

use RuntimeException;

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
        return $outputPath;
    }
}
