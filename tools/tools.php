<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
render_header('PDF Tools', 'Explore PDFForge utilities. Available and upcoming tools are clearly identified.');
$categories = [
    'Convert PDF' => [
        ['PDF to JPG', 'Render each PDF page to a JPG image.', 'pdf-to-jpg.php', true],
        ['JPG to PDF', 'Turn images into a PDF document.', 'jpg-to-pdf.php', false],
        ['PDF to Word', 'Convert PDF documents to editable Word files.', 'pdf-to-word.php', false],
        ['PDF to Excel', 'Extract PDF tables into spreadsheet files.', 'pdf-to-excel.php', false],
        ['PDF to PowerPoint', 'Convert PDF pages into presentations.', 'pdf-to-ppt.php', false],
        ['PDF to Text', 'Extract selectable text from a PDF as a UTF-8 text file.', 'pdf-to-text.php', true],
    ],
    'Organize PDF' => [
        ['Merge PDF', 'Combine multiple PDF documents.', 'merge-pdf.php', false],
        ['Split PDF', 'Extract pages or split a document into parts.', 'split-pdf.php', false],
        ['Rotate PDF', 'Rotate document pages to the right orientation.', 'rotate-pdf.php', false],
    ],
    'Optimize PDF' => [['Compress PDF', 'Reduce PDF size with a chosen quality level.', 'compress-pdf.php', false]],
    'Edit PDF' => [['Watermark PDF', 'Add a text or image watermark to pages.', 'watermark-pdf.php', false]],
    'Security' => [
        ['Protect PDF', 'Encrypt a PDF with a password.', 'protect-pdf.php', false],
        ['Unlock PDF', 'Remove protection when you have the required password and permission.', 'unlock-pdf.php', false],
    ],
];
?>
<main id="main" class="page-main">
    <section class="page-hero"><div class="container"><span class="eyebrow">THE PDF TOOLKIT</span><h1>Everyday PDF tasks,<br><span>made less complicated.</span></h1><p>Use PDF to JPG now. Tools marked “Coming soon” are not yet available for processing.</p>
        <label class="tool-search"><span aria-hidden="true">⌕</span><input type="search" placeholder="Search PDF tools..." data-tool-search aria-label="Search PDF tools"></label>
    </div></section>
    <div class="container tool-directory">
        <?php foreach ($categories as $category => $tools): ?>
            <section class="directory-section"><div class="directory-heading"><h2><?= e($category) ?></h2><span><?= count($tools) ?> <?= count($tools) === 1 ? 'tool' : 'tools' ?></span></div>
                <div class="tool-grid">
                    <?php foreach ($tools as [$name, $description, $href, $ready]): ?>
                        <a class="tool-card" data-tool-card data-search="<?= e(strtolower($name . ' ' . $description . ' ' . $category)) ?>" href="<?= e($href) ?>"><span class="tool-icon"><?= e(mb_substr($name, 0, 1)) ?></span><span class="tool-status <?= $ready ? 'status-live' : '' ?>"><?= $ready ? 'Ready to use' : 'Coming soon' ?></span><h3><?= e($name) ?></h3><p><?= e($description) ?></p><span class="tool-link"><?= $ready ? 'Use tool' : 'Details' ?> <span aria-hidden="true">→</span></span></a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        <p class="search-empty" data-search-empty hidden>No tools match that search.</p>
    </div>
</main>
<?php render_footer(); ?>
