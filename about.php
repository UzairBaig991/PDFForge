<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
render_header('About PDFForge');
?>
<main id="main" class="page-main"><section class="page-hero"><div class="container narrow-container"><span class="eyebrow">ABOUT PDFFORGE</span><h1>Useful PDF tools,<br><span>without the guesswork.</span></h1><p>PDFForge is a self-hosted project focused on dependable document workflows, transparent tool status, and private file handling.</p><a class="button" href="tools/tools.php">Explore tools</a></div></section><section class="section"><div class="container narrow-container prose"><h2>Built to be clear</h2><p>Available tools are backed by real processing. Tools still under development are labeled “Coming soon”; PDFForge does not claim that a conversion happened when it did not.</p><h2>Self-hosted by you</h2><p>Your installation runs on your own XAMPP server. You control its configuration, the PDF engine, file retention, and deployment security.</p></div></section></main>
<?php render_footer(); ?>
