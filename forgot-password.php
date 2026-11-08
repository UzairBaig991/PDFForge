<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
render_header('Password help');
?>
<main id="main" class="page-main auth-main"><section class="auth-card"><span class="eyebrow">ACCOUNT SUPPORT</span><h1>Password reset</h1><p>Password reset emails need an SMTP provider. Email delivery is not configured in this first release, so reset links are not sent.</p><div class="form-alert">Configure an SMTP service before enabling password reset. No account information has been changed.</div><a class="button button-outline" href="login.php">Back to log in</a></section></main>
<?php render_footer(); ?>
