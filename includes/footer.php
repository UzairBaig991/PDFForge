<?php $baseUrl = app_config('app_url'); ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a class="brand" href="<?= e($baseUrl) ?>/index.php"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M7 3h12l7 7v19H7z"/><path d="M19 3v8h7M11 18h11M11 23h8"/></svg></span><span>PDF<span class="brand-accent">Forge</span></span></a>
            <p>Thoughtful tools for your everyday documents.</p>
        </div>
        <div><h2>Tools</h2><a href="<?= e($baseUrl) ?>/tools/tools.php">All PDF tools</a><a href="<?= e($baseUrl) ?>/tools/pdf-to-jpg.php">PDF to JPG</a><a href="<?= e($baseUrl) ?>/tools/merge-pdf.php">Merge PDF</a></div>
        <div><h2>Company</h2><a href="<?= e($baseUrl) ?>/about.php">About</a><a href="<?= e($baseUrl) ?>/contact.php">Contact</a><a href="<?= e($baseUrl) ?>/pricing.php">Pricing</a></div>
        <div><h2>Resources</h2><a href="<?= e($baseUrl) ?>/faq.php">FAQ</a><a href="<?= e($baseUrl) ?>/privacy.php">Privacy</a><a href="<?= e($baseUrl) ?>/terms.php">Terms</a></div>
    </div>
    <div class="container footer-bottom"><span>© <?= date('Y') ?> PDFForge</span><span>Files expire on schedule and are removed by the cleanup task.</span></div>
</footer>
</body>
</html>
