<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
$toolSlug = (string) ($_GET['tool'] ?? ($toolSlug ?? ''));
$tools = [
    'pdf-to-jpg' => ['PDF to JPG', 'Render every page of your PDF as a JPG image.'],
    'jpg-to-pdf' => ['JPG to PDF', 'Arrange JPG or PNG images into a PDF document.'],
    'pdf-to-word' => ['PDF to Word', 'Extract selectable PDF text into an editable Word document.'],
    'pdf-to-excel' => ['PDF to Excel', 'Extract selectable PDF text and columns into a spreadsheet.'],
    'pdf-to-ppt' => ['PDF to PowerPoint', 'Create editable text slides from each PDF page.'],
    'pdf-to-text' => ['PDF to Text', 'Extract selectable text from a PDF as a UTF-8 text file.'],
    'merge-pdf' => ['Merge PDF', 'Combine multiple PDF files into one document.'],
    'split-pdf' => ['Split PDF', 'Extract selected pages or package each page separately.'],
    'compress-pdf' => ['Compress PDF', 'Reduce PDF file size with a selectable quality preset.'],
    'rotate-pdf' => ['Rotate PDF', 'Rotate selected pages or your entire document.'],
    'watermark-pdf' => ['Watermark PDF', 'Add a text watermark to every document page.'],
    'protect-pdf' => ['Protect PDF', 'Encrypt a PDF with a password you choose.'],
    'unlock-pdf' => ['Unlock PDF', 'Remove protection when you have the password and permission.'],
];
$definition = $tools[$toolSlug] ?? null;
if ($definition === null) {
    http_response_code(404);
    require dirname(__DIR__) . '/404.php';
    exit;
}
[$toolName, $toolDescription] = $definition;
render_header($toolName, $toolDescription);
?>
<main id="main" class="page-main tool-detail">
    <div class="container">
        <a class="back-link" href="tools.php">← All tools</a>
        <section class="tool-detail-panel">
            <span class="tool-icon icon-purple"><?= e(mb_substr($toolName, 0, 1)) ?></span>
            <span class="tool-status status-live">Ready to use</span>
            <h1><?= e($toolName) ?></h1><p><?= e($toolDescription) ?></p>
            <?php $uploadTool = $toolSlug; ?>
            <div class="detail-upload"><?php require dirname(__DIR__) . '/includes/upload-form.php'; ?></div>
            <p class="engine-note">Processing requires the server engines documented in the setup guide. Text conversions use selectable PDF text; scanned pages require OCR and are not recognized.</p>
        </section>
    </div>
</main>
<?php render_footer(); ?>
