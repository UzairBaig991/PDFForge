<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
$user = require_login();
$jobs = [];
$usedBytes = 0;
try {
    $statement = db()->prepare(
        'SELECT j.id, j.tool, j.status, j.result_name, j.created_at, f.original_name, f.size_bytes
         FROM jobs j JOIN files f ON f.id = j.file_id
         WHERE j.user_id = :user_id AND j.expires_at > NOW()
         ORDER BY j.created_at DESC LIMIT 10'
    );
    $statement->execute(['user_id' => $user['id']]);
    $jobs = $statement->fetchAll();
    $storage = db()->prepare('SELECT COALESCE(SUM(size_bytes), 0) FROM files WHERE user_id = :user_id AND expires_at > NOW()');
    $storage->execute(['user_id' => $user['id']]);
    $usedBytes = (int) $storage->fetchColumn();
} catch (Throwable $exception) {
    log_app_error($exception, 'dashboard');
}
render_header('Dashboard');
?>
<main id="main" class="page-main dashboard-main"><div class="container">
    <div class="dashboard-heading"><div><span class="eyebrow">YOUR WORKSPACE</span><h1>Welcome, <?= e($user['name']) ?></h1><p>Your recent activity and account tools in one place.</p></div><a class="button" href="tools/tools.php">Use a tool</a></div>
    <div class="dashboard-stats"><article><span>Storage in use</span><strong><?= e(human_size($usedBytes)) ?></strong><small>Temporary files only</small></article><article><span>Recent jobs</span><strong><?= count($jobs) ?></strong><small>Showing up to 10 active records</small></article><article><span>Account</span><strong><?= e($user['role'] === 'admin' ? 'Administrator' : 'Member') ?></strong><small><?= e($user['email']) ?></small></article></div>
    <section class="dashboard-panel"><div class="panel-heading"><div><h2>Recent files</h2><p>Files expire automatically after the configured retention period.</p></div><a href="tools/pdf-to-jpg.php">Convert a PDF →</a></div>
        <?php if (!$jobs): ?><div class="empty-state"><span aria-hidden="true">▤</span><h3>No recent files</h3><p>Your completed tools will appear here.</p><a class="button button-outline" href="tools/tools.php">Explore tools</a></div>
        <?php else: ?><div class="table-wrap"><table><thead><tr><th>File</th><th>Tool</th><th>Size</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody><?php foreach ($jobs as $job): ?><tr><td><?= e($job['original_name']) ?></td><td><?= e(str_replace('-', ' ', $job['tool'])) ?></td><td><?= e(human_size((int) $job['size_bytes'])) ?></td><td><span class="job-status"><?= e($job['status']) ?></span></td><td><?= e(date('M j, Y H:i', strtotime($job['created_at']))) ?></td><td><?php if ($job['status'] === 'COMPLETED'): ?><a class="text-link" href="<?= e(app_config('app_url')) ?>/api/download.php?id=<?= e($job['id']) ?>">Download</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
    <section class="account-links"><a href="profile.php"><strong>Profile &amp; security</strong><span>Update your account details →</span></a><?php if ($user['role'] === 'admin'): ?><a href="admin/index.php"><strong>Administration</strong><span>Manage jobs and system status →</span></a><?php endif; ?><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button type="submit"><strong>Log out</strong><span>End your current session →</span></button></form></section>
</div></main>
<?php render_footer(); ?>
