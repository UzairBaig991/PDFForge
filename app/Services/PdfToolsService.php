<?php
declare(strict_types=1);

namespace PDFForge\Services;

use RuntimeException;
use setasign\Fpdi\Fpdi;
use ZipArchive;

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
            default => throw new RuntimeException('That PDF tool is not available.'),
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
                throw new RuntimeException('Choose valid JPG or PNG image files.');
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
            throw new RuntimeException('Select at least two PDF files to merge.');
        }
        $pdf = new Fpdi();
        foreach ($inputs as $input) {
            $this->appendPages($pdf, $input);
        }
        return $this->writeFpdi($pdf, $directory, $baseName . '-merged.pdf');
    }

    private function split(string $input, array $options, string $directory, string $baseName): array
    {
        $ranges = $this->requestedRanges((string) ($options['pages'] ?? ''), $input);
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
        $degrees = (int) ($options['degrees'] ?? 90);
        if (!in_array($degrees, [90, 180, 270], true)) {
            throw new RuntimeException('Choose a rotation of 90, 180, or 270 degrees.');
        }
        $pageCount = $this->pageCount($input);
        $pages = $this->parsePages((string) ($options['pages'] ?? ''), $pageCount);
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
            throw new RuntimeException('PDF rotation failed. Check that qpdf is installed and the PDF is valid.');
        }
        return $this->moveResult($output, $directory, $baseName . '-rotated.pdf');
    }

    private function watermark(string $input, array $options, string $directory, string $baseName): array
    {
        $text = trim((string) ($options['text'] ?? ''));
        if ($text === '' || mb_strlen($text) > 100) {
            throw new RuntimeException('Enter watermark text of 1 to 100 characters.');
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
        $preset = (string) ($options['quality'] ?? 'ebook');
        if (!in_array($preset, ['screen', 'ebook', 'printer'], true)) {
            throw new RuntimeException('Choose a valid compression quality.');
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
            throw new RuntimeException('PDF compression failed. Check that Ghostscript is installed and the PDF is valid.');
        }
        return $this->moveResult($output, $directory, $baseName . '-compressed.pdf');
    }

    private function protect(string $input, array $options, string $directory, string $baseName): array
    {
        $password = (string) ($options['password'] ?? '');
        if (strlen($password) < 6 || strlen($password) > 128) {
            throw new RuntimeException('Choose a password between 6 and 128 characters.');
        }
        $output = $directory . DIRECTORY_SEPARATOR . 'protected.pdf';
        $engine = new PdfEngine();
        $ownerPassword = bin2hex(random_bytes(24));
        $exitCode = $engine->run([
            $engine->executable('qpdf'), '--encrypt', $password, $ownerPassword, '256', '--', $input, $output,
        ], $directory);
        if ($exitCode !== 0 || !is_file($output)) {
            throw new RuntimeException('PDF protection failed. Check that qpdf is installed and the PDF is valid.');
        }
        return $this->moveResult($output, $directory, $baseName . '-protected.pdf');
    }

    private function unlock(string $input, array $options, string $directory, string $baseName): array
    {
        $password = (string) ($options['password'] ?? '');
        if ($password === '' || strlen($password) > 128) {
            throw new RuntimeException('Enter the PDF password to unlock this document.');
        }
        $output = $directory . DIRECTORY_SEPARATOR . 'unlocked.pdf';
        $engine = new PdfEngine();
        $exitCode = $engine->run([
            $engine->executable('qpdf'), '--password=' . $password, '--decrypt', $input, $output,
        ], $directory);
        if ($exitCode !== 0 || !is_file($output)) {
            throw new RuntimeException('Could not unlock the PDF. Check the password and your permission to modify this document.');
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
        $pages = array_values(array_filter($pages, static fn (string $page): bool => trim($page) !== ''));
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
            throw new RuntimeException('The PDF text could not be read.');
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
            throw new RuntimeException('Enter page numbers or ranges such as 1-3,5.');
        }
        $pages = [];
        foreach (explode(',', $value) as $part) {
            $bounds = array_map('intval', explode('-', $part));
            $start = $bounds[0];
            $end = $bounds[1] ?? $start;
            if ($start < 1 || $end < $start || $end > $count) {
                throw new RuntimeException('A selected page is outside this PDF.');
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

    private function appendPage(Fpdi $pdf, string $input, int $page, int $rotation = 0): void
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
            throw new RuntimeException('The converted file could not be saved.');
        }
        return ['path' => $path, 'name' => $name] + $metadata;
    }

    private function zipResult(array $files, string $directory, string $name, string $prefix, string $extension = 'jpg'): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZIP support is not configured.');
        }
        $path = $directory . DIRECTORY_SEPARATOR . 'result-' . bin2hex(random_bytes(4)) . '.zip';
        $archive = new ZipArchive();
        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The converted files could not be packaged for download.');
        }
        foreach (array_values($files) as $index => $file) {
            if (!$archive->addFile($file, $prefix . ($index + 1) . '.' . $extension)) {
                $archive->close();
                throw new RuntimeException('A converted file could not be added to the download.');
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
            throw new RuntimeException('The office document could not be packaged.');
        }
        foreach ($files as $name => $contents) {
            if (!$archive->addFromString($name, $contents)) {
                $archive->close();
                throw new RuntimeException('The office document could not be packaged.');
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
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
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
        foreach (array_slice(preg_split('/\R/u', trim($text)) ?: [], 0, 45) as $line) {
            if (trim($line) !== '') {
                $paragraphs .= '<a:p><a:r><a:rPr lang="en-US" sz="1800"/><a:t>' . $this->xml(trim($line)) . '</a:t></a:r><a:endParaRPr lang="en-US"/></a:p>';
            }
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
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="PDFForge"><a:themeElements><a:clrScheme name="PDFForge"><a:dk1><a:srgbClr val="000000"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="333333"/></a:dk2><a:lt2><a:srgbClr val="F2F2F2"/></a:lt2><a:accent1><a:srgbClr val="4F46E5"/></a:accent1><a:accent2><a:srgbClr val="7C3AED"/></a:accent2><a:accent3><a:srgbClr val="06B6D4"/></a:accent3><a:accent4><a:srgbClr val="22C55E"/></a:accent4><a:accent5><a:srgbClr val="F59E0B"/></a:accent5><a:accent6><a:srgbClr val="EF4444"/></a:accent6><a:hlink><a:srgbClr val="0000FF"/></a:hlink><a:folHlink><a:srgbClr val="800080"/></a:folHlink></a:clrScheme><a:fontScheme name="PDFForge"><a:majorFont><a:latin typeface="Arial"/></a:majorFont><a:minorFont><a:latin typeface="Arial"/></a:minorFont></a:fontScheme><a:fmtScheme name="PDFForge"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst><a:lnStyleLst/><a:effectStyleLst/><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst></a:fmtScheme></a:themeElements></a:theme>';
    }
}
