<?php
declare(strict_types=1);
http_response_code(404);
require_once __DIR__ . '/app/bootstrap.php';
render_header('Page not found');
?>
<main id="main" class="page-main"><section class="auth-card error-card"><span class="eyebrow">404 · NOT FOUND</span><h1>This page isn’t here.</h1><p>The link may be outdated, or the page may have moved.</p><a class="button" href="<?= e(app_config('app_url')) ?>/index.php">Back to home</a></section></main>
<?php render_footer(); ?>
