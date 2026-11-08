<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
render_header('Reset password');
?>
<main id="main" class="page-main auth-main"><section class="auth-card"><span class="eyebrow">ACCOUNT SUPPORT</span><h1>Password reset unavailable</h1><p>Email-based password reset is not enabled until an SMTP provider and verified reset flow are configured.</p><a class="button button-outline" href="login.php">Back to log in</a></section></main>
<?php render_footer(); ?>
