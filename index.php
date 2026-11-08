<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
render_header('PDFForge', 'Convert and manage PDFs with clear, secure tools. Start with a real PDF to JPG converter.');
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
                </div>
                <div class="trust-row"><span><span aria-hidden="true">✓</span> Private file handling</span><span><span aria-hidden="true">✓</span> Configurable retention</span></div>
            </div>
            <div class="hero-visual">
                <div class="decor decor-one"></div><div class="decor decor-two"></div>
                <section class="upload-card" aria-labelledby="upload-title">
                    <div class="upload-card-heading"><span class="upload-symbol" aria-hidden="true">↑</span><div><h2 id="upload-title">PDF to JPG</h2><p>Turn every page into a clear image</p></div></div>
                    <?php require __DIR__ . '/includes/upload-form.php'; ?>
                    <p class="privacy-note"><span aria-hidden="true">◇</span> Your file is private and removed automatically.</p>
                </section>
                <div class="floating-note"><span class="mini-check" aria-hidden="true">✓</span><span><strong>One page at a time</strong><small>or a ZIP of every page</small></span></div>
            </div>
        </div>
    </section>

    <section class="section tools-section" id="tools">
        <div class="container">
            <div class="section-heading"><span class="eyebrow">TOOLS FOR YOUR WORKFLOW</span><h2>Get more done with your PDFs</h2><p>Start with the converter that’s ready today. More tools are clearly marked as they’re built.</p></div>
            <div class="tool-grid">
                <a class="tool-card featured-tool" href="tools/pdf-to-jpg.php"><span class="tool-icon icon-purple">▧</span><span class="tool-status status-live">Ready to use</span><h3>PDF to JPG</h3><p>Render PDF pages as individual JPG images or a single ZIP.</p><span class="tool-link">Convert PDF <span aria-hidden="true">→</span></span></a>
                <a class="tool-card" href="tools/merge-pdf.php"><span class="tool-icon icon-blue">▤</span><span class="tool-status">Coming soon</span><h3>Merge PDF</h3><p>Combine multiple documents into one organized file.</p><span class="tool-link">View tool <span aria-hidden="true">→</span></span></a>
                <a class="tool-card" href="tools/compress-pdf.php"><span class="tool-icon icon-green">⌁</span><span class="tool-status">Coming soon</span><h3>Compress PDF</h3><p>Reduce document size while keeping useful quality.</p><span class="tool-link">View tool <span aria-hidden="true">→</span></span></a>
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
                <article class="feature-item"><span class="feature-icon">⚡</span><h3>Real processing</h3><p>PDF to JPG uses Poppler to render actual pages—no extensions or renamed files masquerading as conversions.</p></article>
                <article class="feature-item"><span class="feature-icon">◷</span><h3>Automatic cleanup</h3><p>Files expire after the configured retention period; schedule the included cleanup task to remove them from disk.</p></article>
                <article class="feature-item"><span class="feature-icon">▱</span><h3>Works across devices</h3><p>A responsive layout, keyboard-friendly controls, and a real dark theme make the tools comfortable to use.</p></article>
            </div>
        </div>
    </section>

    <section class="section steps-section">
        <div class="container">
            <div class="section-heading"><span class="eyebrow">THREE SIMPLE STEPS</span><h2>From file to finished</h2></div>
            <div class="steps-grid"><article><span>01</span><h3>Upload</h3><p>Choose your PDF. We check its type and size before saving it.</p></article><article><span>02</span><h3>Process</h3><p>The configured PDF engine renders each page to an image.</p></article><article><span>03</span><h3>Download</h3><p>Get a JPG for one page or a ZIP containing all pages.</p></article></div>
        </div>
    </section>

    <section class="section faq-section" id="faq">
        <div class="container faq-layout"><div class="section-heading"><span class="eyebrow">GOOD TO KNOW</span><h2>Frequently asked questions</h2><p>Some quick answers about PDFForge and file handling.</p><a class="text-link" href="faq.php">Visit the FAQ <span aria-hidden="true">→</span></a></div>
            <div class="faq-list">
                <details><summary>What is PDFForge?<span aria-hidden="true">+</span></summary><p>PDFForge is a self-hosted collection of PDF utilities. PDF to JPG is the first implemented tool; other tools are labeled “Coming soon” until their real processing is in place.</p></details>
                <details><summary>How do I convert PDF pages to JPG?<span aria-hidden="true">+</span></summary><p>Select a PDF from the upload area. Once the server’s Poppler engine is configured, PDFForge renders the pages and gives you a JPG or a ZIP of images.</p></details>
                <details><summary>Are my files kept private?<span aria-hidden="true">+</span></summary><p>Files are stored in protected storage and downloads are authorized against the session or signed-in account that created the job. The local administrator remains responsible for server security.</p></details>
                <details><summary>How long are uploaded files stored?<span aria-hidden="true">+</span></summary><p>The default retention is one hour. Run the included cleanup script on a schedule so expired files are physically removed.</p></details>
                <details><summary>What is the maximum file size?<span aria-hidden="true">+</span></summary><p>The default application limit is 50 MB. PHP and Apache upload limits must also be set to at least that size.</p></details>
                <details><summary>Can I use PDFForge on mobile?</summary><p>Yes. The interface adapts for phones and tablets, including a collapsible navigation menu and touch-friendly upload controls.</p></details>
            </div>
        </div>
    </section>

    <section class="cta-section"><div class="container cta-inner"><div><span class="eyebrow">START WITH A REAL TOOL</span><h2>Ready to work smarter with PDFs?</h2><p>Try the PDF-to-JPG converter or explore the tools in development.</p></div><a class="button button-light" href="tools/pdf-to-jpg.php">Explore PDF tools <span aria-hidden="true">→</span></a></div></section>
</main>
<?php render_footer(); ?>
