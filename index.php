<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$route = strtolower((string) ($_GET['route'] ?? ''));
if ($route === '') {
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $appPath = rtrim((string) (parse_url((string) app_config('app_url'), PHP_URL_PATH) ?: ''), '/');
    $route = rtrim($path, '/') === $appPath ? 'home' : strtolower(pathinfo($path, PATHINFO_FILENAME));
    if ($route === '') $route = 'home';
}
$publicRoutes = ['home'=>'page_home','index'=>'page_home','about'=>'page_about','contact'=>'page_contact','faq'=>'page_faq','pricing'=>'page_pricing','privacy'=>'page_privacy','terms'=>'page_terms','tools'=>'page_tools','403'=>'page_forbidden','404'=>'page_not_found','500'=>'page_server_error'];
if (isset($publicRoutes[$route])) $publicRoutes[$route]();
elseif ($route === 'tool') page_tool((string) ($_GET['tool'] ?? ''));
else render_error_page(404);

function page_home(): void
{
render_header('PDFForge', 'Convert and manage PDFs with secure tools for documents, images, and office formats.');
?>
<main id="main">
    <section class="hero-section">
        <div class="container hero-grid">
            <div class="hero-copy">
                <span class="eyebrow"><span class="status-dot"></span> Fast · Private · Straightforward</span>
                <h1>Powerful PDF tools,<br><span>made simple.</span></h1>
                <p>Convert, organize, and manage your documents with practical tools that do exactly what they say.</p>
                <div class="hero-actions">
                    <a class="button" href="tools/tools.php">Explore PDF tools <span aria-hidden="true">→</span></a>
                    <a class="button button-outline" href="tools/pdf-to-jpg.php">Try PDF to JPG</a>
                    <a class="button button-outline" href="tools/pdf-to-word.php">Try PDF to Word</a>
                </div>
                <div class="trust-row"><span><span aria-hidden="true">✓</span> Private file handling</span><span><span aria-hidden="true">✓</span> Configurable retention</span></div>
            </div>
            <div class="hero-visual">
                <div class="decor decor-one"></div><div class="decor decor-two"></div>
                <section class="upload-card" aria-labelledby="upload-title">
                    <div class="upload-card-heading"><span class="upload-symbol" aria-hidden="true">↑</span><div><h2 id="upload-title">PDF to JPG</h2><p data-home-tool-description>Turn every page into a clear image</p></div></div>
                    <div class="home-tool-switch" role="tablist" aria-label="Choose a PDF tool">
                        <button class="home-tool-tab is-active" type="button" role="tab" aria-selected="true" data-home-tool="pdf-to-jpg">PDF to JPG</button>
                        <button class="home-tool-tab" type="button" role="tab" aria-selected="false" data-home-tool="pdf-to-word">PDF to Word</button>
                    </div>
                    <?php render_upload_form($uploadTool ?? null); ?>
                    <p class="privacy-note"><span aria-hidden="true">◇</span> Your file is private and removed automatically.</p>
                </section>
                <div class="floating-note"><span class="mini-check" aria-hidden="true">✓</span><span><strong data-home-note-title>One page at a time</strong><small data-home-note-description>or a ZIP of every page</small></span></div>
            </div>
        </div>
    </section>

    <section class="section tools-section" id="tools">
        <div class="container">
            <div class="section-heading"><span class="eyebrow">TOOLS FOR YOUR WORKFLOW</span><h2>Get more done with your PDFs</h2><p>Convert, organize, optimize, and protect your documents with the complete toolkit.</p></div>
            <div class="tool-grid">
                <a class="tool-card featured-tool" href="tools/pdf-to-jpg.php"><span class="tool-icon icon-purple">▧</span><span class="tool-status status-live">Ready to use</span><h3>PDF to JPG</h3><p>Render PDF pages as individual JPG images or a single ZIP.</p><span class="tool-link">Convert PDF <span aria-hidden="true">→</span></span></a>
                <a class="tool-card featured-tool" href="tools/pdf-to-word.php"><span class="tool-icon icon-blue">W</span><span class="tool-status status-live">Ready to use</span><h3>PDF to Word</h3><p>Extract selectable PDF text into an editable Word document.</p><span class="tool-link">Convert PDF <span aria-hidden="true">→</span></span></a>
                <a class="tool-card" href="tools/merge-pdf.php"><span class="tool-icon icon-blue">▤</span><span class="tool-status status-live">Ready to use</span><h3>Merge PDF</h3><p>Combine multiple documents into one organized file.</p><span class="tool-link">Use tool <span aria-hidden="true">→</span></span></a>
                <a class="tool-card" href="tools/compress-pdf.php"><span class="tool-icon icon-green">⌁</span><span class="tool-status status-live">Ready to use</span><h3>Compress PDF</h3><p>Reduce document size with a selectable quality preset.</p><span class="tool-link">Use tool <span aria-hidden="true">→</span></span></a>
                <a class="tool-card" href="tools/pdf-to-text.php"><span class="tool-icon icon-orange">T</span><span class="tool-status status-live">Ready to use</span><h3>PDF to Text</h3><p>Extract selectable text from a PDF into a UTF-8 text file.</p><span class="tool-link">Extract text <span aria-hidden="true">→</span></span></a>
            </div>
            <div class="center-action"><a class="text-link" href="tools/tools.php">Browse all tools <span aria-hidden="true">→</span></a></div>
        </div>
    </section>

    <section class="section feature-section" id="features">
        <div class="container">
            <div class="section-heading"><span class="eyebrow">BUILT WITH CARE</span><h2>Useful tools. Clear expectations.</h2><p>We keep the workflow simple and tell you exactly what happens to your files.</p></div>
            <div class="feature-grid">
                <article class="feature-item"><span class="feature-icon">⌑</span><h3>Private by design</h3><p>Files are kept in Apache-protected storage and served only through an ownership-checked endpoint.</p></article>
                <article class="feature-item"><span class="feature-icon">⚡</span><h3>Real processing</h3><p>Every listed tool runs a real conversion or document operation using the configured PDF engines.</p></article>
                <article class="feature-item"><span class="feature-icon">◷</span><h3>Automatic cleanup</h3><p>Files expire after the configured retention period; schedule the included cleanup task to remove them from disk.</p></article>
                <article class="feature-item"><span class="feature-icon">▱</span><h3>Works across devices</h3><p>A responsive layout, keyboard-friendly controls, and a real dark theme make the tools comfortable to use.</p></article>
            </div>
        </div>
    </section>

    <section class="section steps-section">
        <div class="container">
            <div class="section-heading"><span class="eyebrow">THREE SIMPLE STEPS</span><h2>From file to finished</h2></div>
            <div class="steps-grid"><article><span>01</span><h3>Upload</h3><p>Choose a PDF or image file. We validate its type and size before saving.</p></article><article><span>02</span><h3>Process</h3><p>The configured engine runs your selected conversion or document operation.</p></article><article><span>03</span><h3>Download</h3><p>Download your converted document or processed PDF.</p></article></div>
        </div>
    </section>

    <section class="section faq-section" id="faq">
        <div class="container faq-layout"><div class="section-heading"><span class="eyebrow">GOOD TO KNOW</span><h2>Frequently asked questions</h2><p>Some quick answers about PDFForge and file handling.</p><a class="text-link" href="faq.php">Visit the FAQ <span aria-hidden="true">→</span></a></div>
            <div class="faq-list">
                <details><summary>What is PDFForge?<span aria-hidden="true">+</span></summary><p>PDFForge is a self-hosted collection of PDF utilities for conversion, page organization, optimization, and document security.</p></details>
                <details><summary>How do I convert PDF pages to JPG?<span aria-hidden="true">+</span></summary><p>Select a PDF from the upload area. Once the server’s Poppler engine is configured, PDFForge renders the pages and gives you a JPG or a ZIP of images.</p></details>
                <details><summary>Are my files kept private?<span aria-hidden="true">+</span></summary><p>Files are stored in protected storage and downloads are authorized against the session or signed-in account that created the job. The local administrator remains responsible for server security.</p></details>
                <details><summary>How long are uploaded files stored?<span aria-hidden="true">+</span></summary><p>The default retention is one hour. Run the included cleanup script on a schedule so expired files are physically removed.</p></details>
                <details><summary>What is the maximum file size?<span aria-hidden="true">+</span></summary><p>The default application limit is 50 MB. PHP and Apache upload limits must also be set to at least that size.</p></details>
                <details><summary>Can I use PDFForge on mobile?</summary><p>Yes. The interface adapts for phones and tablets, including a collapsible navigation menu and touch-friendly upload controls.</p></details>
            </div>
        </div>
    </section>

    <section class="cta-section"><div class="container cta-inner"><div><span class="eyebrow">YOUR COMPLETE PDF TOOLKIT</span><h2>Ready to work smarter with PDFs?</h2><p>Convert, organize, and secure your documents with the available tools.</p></div><a class="button button-light" href="tools/pdf-to-jpg.php">Explore PDF tools <span aria-hidden="true">→</span></a></div></section>
</main>
<?php render_footer(); ?>
<?php
}

function page_about(): void
{
render_header('About PDFForge');
?>
<main id="main" class="page-main"><section class="page-hero"><div class="container narrow-container"><span class="eyebrow">ABOUT PDFFORGE</span><h1>Useful PDF tools,<br><span>without the guesswork.</span></h1><p>PDFForge is a self-hosted project focused on dependable document workflows, transparent tool status, and private file handling.</p><a class="button" href="tools/tools.php">Explore tools</a></div></section><section class="section"><div class="container narrow-container prose"><h2>Built to be clear</h2><p>Every listed document tool runs a real conversion or PDF operation. Results depend on the engines configured by your server administrator.</p><h2>Self-hosted by you</h2><p>Your installation runs on your own XAMPP server. You control its configuration, the PDF engines, file retention, and deployment security.</p></div></section></main>
<?php render_footer(); ?>
<?php
}

function page_contact(): void
{
render_header('Contact');
?>
<main id="main" class="page-main"><section class="page-hero"><div class="container narrow-container"><span class="eyebrow">CONTACT</span><h1>Need a hand?</h1><p>This self-hosted starter does not send contact messages. Add your preferred support mailbox or contact service before enabling a public contact form.</p><div class="coming-panel"><span aria-hidden="true">i</span><div><h2>No message is sent from this page</h2><p>We avoid presenting a form that silently discards your message. For setup help, see the project README.</p></div></div><a class="button button-outline" href="faq.php">Read the FAQ</a></div></section></main>
<?php render_footer(); ?>
<?php
}

function page_faq(): void
{
render_header('Frequently asked questions');
?>
<main id="main" class="page-main"><section class="page-hero"><div class="container narrow-container"><span class="eyebrow">HELP CENTER</span><h1>Frequently asked questions</h1><p>How PDFForge works, what is ready, and what you need to run it.</p></div></section><section class="section"><div class="container narrow-container faq-list">
<details><summary>What is PDFForge?<span aria-hidden="true">+</span></summary><p>A self-hosted toolkit for converting, organizing, optimizing, and securing PDF files.</p></details>
<details><summary>How do I convert PDF to Word?<span aria-hidden="true">+</span></summary><p>Configure Poppler on the server, choose PDF to Word, and upload your file. Selectable text is extracted into an editable DOCX; scanned text and exact visual layout are not reconstructed.</p></details>
<details><summary>How do I convert PDF to JPG?<span aria-hidden="true">+</span></summary><p>Configure Poppler’s pdftoppm executable, create the MySQL schema, and upload your PDF. Single-page PDFs download as a JPG; multi-page PDFs download as a ZIP.</p></details>
<details><summary>Can I extract text from scanned PDFs?</summary><p>PDF to Text extracts selectable text using Poppler. It does not include OCR, so scanned pages without a text layer will not produce recognized words.</p></details>
<details><summary>What server engines are needed?</summary><p>Install Poppler for conversions, QPDF for page rotation and password operations, and Ghostscript for compression. Composer installs the PHP libraries used for PDF editing.</p></details>
<details><summary>Are my files secure?<span aria-hidden="true">+</span></summary><p>Files are placed in protected server-side storage, given random storage names, and downloaded only after an ownership check. No local application can guarantee the security of a misconfigured server.</p></details>
<details><summary>How long are uploaded files stored?<span aria-hidden="true">+</span></summary><p>One hour by default. Schedule the cleanup script to remove expired data from disk.</p></details>
<details><summary>What is the maximum file size?</summary><p>50 MB by default. Increase the application limit only after also adjusting PHP and Apache limits.</p></details>
<details><summary>Can I use PDFForge on mobile?</summary><p>Yes, the site layout and upload controls are responsive.</p></details>
<details><summary>Is PDFForge free?</summary><p>This source project has no billing functionality. Your hosting and infrastructure costs depend on your setup.</p></details>
</div></section></main>
<?php render_footer(); ?>
<?php
}

function page_pricing(): void
{
render_header('Plans');
?>
<main id="main" class="page-main"><section class="page-hero"><div class="container narrow-container"><span class="eyebrow">SIMPLE BY DESIGN</span><h1>No pretend plans or checkout.</h1><p>PDFForge is currently a self-hosted starter application. Paid plans, billing, and payment processing are not implemented.</p></div></section><section class="section"><div class="container narrow-container"><div class="coming-panel"><span aria-hidden="true">◇</span><div><h2>Self-host the project</h2><p>Run it locally with XAMPP and configure the service limits for your installation. This page will only offer subscriptions once real plan and payment support exists.</p></div></div></div></section></main>
<?php render_footer(); ?>
<?php
}

function page_privacy(): void
{
render_header('Privacy');
?>
<main id="main" class="page-main"><section class="page-hero"><div class="container narrow-container"><span class="eyebrow">PRIVACY</span><h1>File handling, explained.</h1><p>This starter runs on the server where it is installed. The site operator controls the server, database, backups, logs, and configuration.</p></div></section><section class="section"><div class="container narrow-container prose"><h2>Uploaded documents</h2><p>Uploads are checked on the server and stored in protected storage with random internal names. Download requests are checked against the session or signed-in account that created the job. The default expiry is one hour.</p><h2>Automatic deletion</h2><p>Expired records and files are removed by the included cleanup script when it is scheduled. If that script is not run, files may remain on disk longer than their expiry timestamp.</p><h2>Accounts</h2><p>Account credentials are stored as password hashes. This starter does not include email verification, password reset email delivery, analytics, or third-party tracking.</p><h2>Operator responsibilities</h2><p>Before public deployment, configure HTTPS, mail handling if needed, database access controls, secure backups, and your legal notices. This template is not legal advice.</p></div></section></main>
<?php render_footer(); ?>
<?php
}

function page_terms(): void
{
render_header('Terms');
?>
<main id="main" class="page-main"><section class="page-hero"><div class="container narrow-container"><span class="eyebrow">TERMS</span><h1>Use the tools responsibly.</h1><p>PDFForge is an open, self-hosted software starter. The operator must publish terms appropriate to their service before accepting public users.</p></div></section><section class="section"><div class="container narrow-container prose"><h2>Your documents</h2><p>Only upload files you are authorized to process. Respect applicable privacy, copyright, and data-protection rules.</p><h2>Processing availability</h2><p>Processing depends on the local server and configured dependencies. No service-level guarantee is made by this starter project.</p><h2>Before launch</h2><p>Replace this informational page with terms reviewed for the jurisdiction and deployment where the application will be used.</p></div></section></main>
<?php render_footer(); ?>
<?php
}

function page_tools(): void
{
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
<?php
}

function page_tool(string $toolSlug): void
{
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
    render_error_page(404);
    return;
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
            <div class="detail-upload"><?php render_upload_form($uploadTool ?? null); ?></div>
            <p class="engine-note">Processing requires the server engines documented in the setup guide. Text conversions use selectable PDF text; scanned pages require OCR and are not recognized.</p>
        </section>
    </div>
</main>
<?php render_footer(); ?>
<?php
}

function page_forbidden(): void
{
http_response_code(403);
render_header('Access denied');
?>
<main id="main" class="page-main"><section class="auth-card error-card"><span class="eyebrow">403 · ACCESS DENIED</span><h1>You don’t have access.</h1><p>This area is restricted to authorized accounts.</p><a class="button button-outline" href="<?= e(app_config('app_url')) ?>/dashboard.php">Return to dashboard</a></section></main>
<?php render_footer(); ?>
<?php
}

function page_not_found(): void
{
http_response_code(404);
render_header('Page not found');
?>
<main id="main" class="page-main"><section class="auth-card error-card"><span class="eyebrow">404 · NOT FOUND</span><h1>This page isn’t here.</h1><p>The link may be outdated, or the page may have moved.</p><a class="button" href="<?= e(app_config('app_url')) ?>/index.php">Back to home</a></section></main>
<?php render_footer(); ?>
<?php
}

function page_server_error(): void
{
http_response_code(500);
render_header('Something went wrong');
?>
<main id="main" class="page-main"><section class="auth-card error-card"><span class="eyebrow">500 · SERVER ERROR</span><h1>Something went wrong.</h1><p>Please try again. The server has not exposed internal diagnostic details.</p><a class="button button-outline" href="<?= e(app_config('app_url')) ?>/index.php">Back to home</a></section></main>
<?php render_footer(); ?>
<?php
}
