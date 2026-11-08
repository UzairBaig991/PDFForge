<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
render_header('PDF Tools', 'Explore PDFForge utilities. Available and upcoming tools are clearly identified.');
$categories = [
    'Convert PDF' => [
        ['PDF to JPG', 'Render each PDF page to a JPG image.', 'pdf-to-jpg.php', true],
        ['JPG to PDF', 'Arrange JPG or PNG images into a PDF document.', 'jpg-to-pdf.php', true],
        ['PDF to Word', 'Extract selectable PDF text into an editable Word document.', 'pdf-to-word.php', true],
        ['PDF to Excel', 'Extract selectable PDF text and columns into a spreadsheet.', 'pdf-to-excel.php', true],
        ['PDF to PowerPoint', 'Create editable text slides from each PDF page.', 'pdf-to-ppt.php', true],
        ['PDF to Text', 'Extract selectable text from a PDF as a UTF-8 text file.', 'pdf-to-text.php', true],
    ],
    'Organize PDF' => [
        ['Merge PDF', 'Combine multiple PDF documents.', 'merge-pdf.php', true],
        ['Split PDF', 'Extract selected pages or package each page separately.', 'split-pdf.php', true],
        ['Rotate PDF', 'Rotate selected pages or the entire document.', 'rotate-pdf.php', true],
    ],
    'Optimize PDF' => [['Compress PDF', 'Reduce PDF size with a chosen quality level.', 'compress-pdf.php', true]],
    'Edit PDF' => [['Watermark PDF', 'Add a text watermark to every page.', 'watermark-pdf.php', true]],
    'Security' => [
        ['Protect PDF', 'Encrypt a PDF with a password.', 'protect-pdf.php', true],
        ['Unlock PDF', 'Remove protection when you have the required password and permission.', 'unlock-pdf.php', true],
    ],
];
?>
<main id="main" class="page-main">
    <section class="page-hero"><div class="container"><span class="eyebrow">THE PDF TOOLKIT</span><h1>Everyday PDF tasks,<br><span>made less complicated.</span></h1><p>All listed tools are available. Processing depends on the PDF engines configured on your server.</p>
        <label class="tool-search"><span aria-hidden="true">⌕</span><input type="search" placeholder="Search PDF tools..." data-tool-search aria-label="Search PDF tools"></label>
    </div></section>
    <div class="container tool-directory">
        <?php foreach ($categories as $category => $tools): ?>
            <section class="directory-section"><div class="directory-heading"><h2><?= e($category) ?></h2><span><?= count($tools) ?> <?= count($tools) === 1 ? 'tool' : 'tools' ?></span></div>
                <div class="tool-grid">
                    <?php foreach ($tools as [$name, $description, $href, $ready]): ?>
                        <a class="tool-card" data-tool-card data-search="<?= e(strtolower($name . ' ' . $description . ' ' . $category)) ?>" href="<?= e($href) ?>"><span class="tool-icon"><?= e(mb_substr($name, 0, 1)) ?></span><span class="tool-status status-live">Ready to use</span><h3><?= e($name) ?></h3><p><?= e($description) ?></p><span class="tool-link">Use tool <span aria-hidden="true">→</span></span></a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        <p class="search-empty" data-search-empty hidden>No tools match that search.</p>
    </div>
</main>
<?php render_footer(); ?>
