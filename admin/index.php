<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
$user = require_login();
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    require dirname(__DIR__) . '/403.php';
    exit;
}
$counts = ['users' => 0, 'jobs' => 0, 'queued' => 0];
try {
    $counts['users'] = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $counts['jobs'] = (int) db()->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
    $counts['queued'] = (int) db()->query("SELECT COUNT(*) FROM jobs WHERE status IN ('QUEUED', 'PROCESSING')")->fetchColumn();
} catch (Throwable $exception) {
    log_app_error($exception, 'admin-dashboard');
}
render_header('Administration');
?>
<main id="main" class="page-main"><div class="container"><a class="back-link" href="../dashboard.php">← Dashboard</a><div class="section-heading left-heading"><span class="eyebrow">ADMINISTRATION</span><h1>System overview</h1><p>Admin access is restricted to accounts explicitly assigned the admin role.</p></div><div class="dashboard-stats"><article><span>Accounts</span><strong><?= $counts['users'] ?></strong></article><article><span>Total jobs</span><strong><?= $counts['jobs'] ?></strong></article><article><span>Active jobs</span><strong><?= $counts['queued'] ?></strong></article></div><section class="dashboard-panel"><h2>Storage &amp; maintenance</h2><p>Upload limits, retention, and PDF engine location are controlled through the server’s protected <code>.env</code> configuration.</p><p>Use <code>php cron/cleanup.php</code> to remove expired files and records.</p></section></div></main>
<?php render_footer(); ?>
