<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
$tools = [
    'pdf-to-jpg' => ['PDF to JPG', 'Render every page of your PDF as a JPG image.', true],
    'jpg-to-pdf' => ['JPG to PDF', 'Arrange images into a PDF document.', false],
    'pdf-to-word' => ['PDF to Word', 'Convert PDF documents into editable Word files.', false],
    'pdf-to-excel' => ['PDF to Excel', 'Extract tables into an Excel workbook.', false],
    'pdf-to-ppt' => ['PDF to PowerPoint', 'Create a presentation from PDF pages.', false],
    'pdf-to-text' => ['PDF to Text', 'Extract selectable text from a PDF as a UTF-8 text file.', true],
    'merge-pdf' => ['Merge PDF', 'Combine multiple PDF files into one document.', false],
    'split-pdf' => ['Split PDF', 'Extract selected pages or split a PDF into parts.', false],
    'compress-pdf' => ['Compress PDF', 'Reduce PDF file size using actual document processing.', false],
    'rotate-pdf' => ['Rotate PDF', 'Rotate selected pages or your entire document.', false],
    'watermark-pdf' => ['Watermark PDF', 'Add a text or image watermark to document pages.', false],
    'protect-pdf' => ['Protect PDF', 'Encrypt a PDF with a password you choose.', false],
    'unlock-pdf' => ['Unlock PDF', 'Remove protection only when the required password and permission are available.', false],
];
$definition = $tools[$toolSlug] ?? null;
if ($definition === null) {
    http_response_code(404);
    require dirname(__DIR__) . '/404.php';
    exit;
}
[$toolName, $toolDescription, $ready] = $definition;
render_header($toolName, $toolDescription);
?>
<main id="main" class="page-main tool-detail">
    <div class="container">
        <a class="back-link" href="tools.php">← All tools</a>
        <section class="tool-detail-panel">
            <span class="tool-icon icon-purple"><?= e(mb_substr($toolName, 0, 1)) ?></span>
            <span class="tool-status <?= $ready ? 'status-live' : '' ?>"><?= $ready ? 'Ready to use' : 'Coming soon' ?></span>
            <h1><?= e($toolName) ?></h1><p><?= e($toolDescription) ?></p>
            <?php if ($ready): ?>
                <?php $uploadTool = $toolSlug; ?>
                <div class="detail-upload"><?php require dirname(__DIR__) . '/includes/upload-form.php'; ?></div>
                <p class="engine-note">This tool needs Poppler configured on the server. PDF to Text extracts selectable text; it cannot OCR scanned pages.</p>
            <?php else: ?>
                <div class="coming-panel"><span aria-hidden="true">✦</span><div><h2>This tool is still in development</h2><p>Conversion is not enabled yet. We’ll only mark it ready when the real processing workflow is implemented.</p></div></div>
                <a class="button button-outline" href="tools.php">Explore available tools</a>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php render_footer(); ?>
