<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
render_header('Frequently asked questions');
?>
<main id="main" class="page-main"><section class="page-hero"><div class="container narrow-container"><span class="eyebrow">HELP CENTER</span><h1>Frequently asked questions</h1><p>How PDFForge works, what is ready, and what you need to run it.</p></div></section><section class="section"><div class="container narrow-container faq-list">
<details><summary>What is PDFForge?<span aria-hidden="true">+</span></summary><p>A self-hosted PDF utility starter with a real PDF-to-JPG conversion path and account scaffolding.</p></details>
<details><summary>How do I convert PDF to Word?<span aria-hidden="true">+</span></summary><p>PDF-to-Word is not available in this release. It is labeled “Coming soon”; no DOCX is generated until a real conversion engine is integrated.</p></details>
<details><summary>How do I convert PDF to JPG?<span aria-hidden="true">+</span></summary><p>Configure Poppler’s pdftoppm executable, create the MySQL schema, and upload your PDF. Single-page PDFs download as a JPG; multi-page PDFs download as a ZIP.</p></details>
<details><summary>Can I extract text from scanned PDFs?</summary><p>PDF to Text extracts selectable text using Poppler. It does not include OCR, so scanned pages without a text layer will not produce recognized words.</p></details>
<details><summary>Are my files secure?<span aria-hidden="true">+</span></summary><p>Files are placed in protected server-side storage, given random storage names, and downloaded only after an ownership check. No local application can guarantee the security of a misconfigured server.</p></details>
<details><summary>How long are uploaded files stored?<span aria-hidden="true">+</span></summary><p>One hour by default. Schedule the cleanup script to remove expired data from disk.</p></details>
<details><summary>What is the maximum file size?</summary><p>50 MB by default. Increase the application limit only after also adjusting PHP and Apache limits.</p></details>
<details><summary>Can I use PDFForge on mobile?</summary><p>Yes, the site layout and upload controls are responsive.</p></details>
<details><summary>Is PDFForge free?</summary><p>This source project has no billing functionality. Your hosting and infrastructure costs depend on your setup.</p></details>
</div></section></main>
<?php render_footer(); ?>
