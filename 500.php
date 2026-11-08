<?php
declare(strict_types=1);
http_response_code(500);
require_once __DIR__ . '/app/bootstrap.php';
render_header('Something went wrong');
?>
<main id="main" class="page-main"><section class="auth-card error-card"><span class="eyebrow">500 · SERVER ERROR</span><h1>Something went wrong.</h1><p>Please try again. The server has not exposed internal diagnostic details.</p><a class="button button-outline" href="<?= e(app_config('app_url')) ?>/index.php">Back to home</a></section></main>
<?php render_footer(); ?>
