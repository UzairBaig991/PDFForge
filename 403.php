<?php
declare(strict_types=1);
http_response_code(403);
require_once __DIR__ . '/app/bootstrap.php';
render_header('Access denied');
?>
<main id="main" class="page-main"><section class="auth-card error-card"><span class="eyebrow">403 · ACCESS DENIED</span><h1>You don’t have access.</h1><p>This area is restricted to authorized accounts.</p><a class="button button-outline" href="<?= e(app_config('app_url')) ?>/dashboard.php">Return to dashboard</a></section></main>
<?php render_footer(); ?>
