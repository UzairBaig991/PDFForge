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
