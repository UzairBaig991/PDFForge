<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$route = strtolower((string) ($_GET['route'] ?? ''));
if ($route === '') {
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $route = strtolower(pathinfo($path, PATHINFO_FILENAME));
    if ($route === 'index' && str_contains(strtolower($path), '/admin/')) $route = 'admin';
}
$accountRoutes = ['login'=>'account_login','register'=>'account_register','forgot-password'=>'account_forgot_password','reset-password'=>'account_reset_password','logout'=>'account_logout','profile'=>'account_profile','dashboard'=>'account_dashboard','admin'=>'account_admin'];
if (isset($accountRoutes[$route])) $accountRoutes[$route]();
else render_error_page(404);

function account_login(): void
{
if (current_user()) {
    header('Location: ' . app_config('app_url') . '/dashboard.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provided = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals(csrf_token(), $provided)) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif ((int) ($_SESSION['login_attempts'] ?? 0) >= 8 && (int) ($_SESSION['login_window'] ?? 0) > time() - 900) {
        $error = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
    } else {
        try {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $statement = db()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1');
            $statement->execute(['email' => $email]);
            $account = $statement->fetch();
            if ($account && password_verify((string) ($_POST['password'] ?? ''), $account['password_hash'])) {
                session_regenerate_id(true);
                unset($account['password_hash']);
                $_SESSION['user'] = $account;
                $_SESSION['login_attempts'] = 0;
                header('Location: ' . app_config('app_url') . '/dashboard.php');
                exit;
            }
            if (empty($_SESSION['login_window']) || (int) $_SESSION['login_window'] <= time() - 900) {
                $_SESSION['login_window'] = time();
                $_SESSION['login_attempts'] = 0;
            }
            $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
            $error = 'Email or password is incorrect.';
        } catch (Throwable $exception) {
            log_app_error($exception, 'login');
            $error = 'Sign in is temporarily unavailable. Check the database configuration.';
        }
    }
}
render_header('Log in');
?>
<main id="main" class="page-main auth-main"><section class="auth-card"><span class="eyebrow">WELCOME BACK</span><h1>Log in to PDFForge</h1><p>Sign in to see your recent tools and files.</p>
    <?php if ($error !== ''): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Email address<input name="email" type="email" autocomplete="email" required maxlength="190" value="<?= e($_POST['email'] ?? '') ?>"></label>
        <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
        <button class="button" type="submit">Log in</button>
    </form>
    <p class="form-footnote"><a href="forgot-password.php">Forgot password?</a></p>
    <p class="form-footnote">New to PDFForge? <a href="register.php">Create an account</a></p>
</section></main>
<?php render_footer(); ?>
<?php
}

function account_register(): void
{
if (current_user()) {
    header('Location: ' . app_config('app_url') . '/dashboard.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Refresh this page and try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid name and email address.';
        } elseif (strlen($password) < 10) {
            $error = 'Choose a password with at least 10 characters.';
        } else {
            try {
                $insert = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)');
                $insert->execute(['name' => $name, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
                session_regenerate_id(true);
                $_SESSION['user'] = ['id' => (int) db()->lastInsertId(), 'name' => $name, 'email' => $email, 'role' => 'user'];
                header('Location: ' . app_config('app_url') . '/dashboard.php');
                exit;
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'An account with that email address already exists.';
                } else {
                    log_app_error($exception, 'registration');
                    $error = 'Registration is temporarily unavailable. Check the database configuration.';
                }
            }
        }
    }
}
render_header('Create your account');
?>
<main id="main" class="page-main auth-main"><section class="auth-card"><span class="eyebrow">GET STARTED</span><h1>Create your account</h1><p>Your account keeps your PDFForge activity in one place.</p>
    <?php if ($error !== ''): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Your name<input name="name" autocomplete="name" required maxlength="120" value="<?= e($_POST['name'] ?? '') ?>"></label>
        <label>Email address<input name="email" type="email" autocomplete="email" required maxlength="190" value="<?= e($_POST['email'] ?? '') ?>"></label>
        <label>Password<input name="password" type="password" autocomplete="new-password" minlength="10" required><small>Use at least 10 characters.</small></label>
        <button class="button" type="submit">Create account</button>
    </form>
    <p class="form-footnote">Already registered? <a href="login.php">Log in</a></p>
</section></main>
<?php render_footer(); ?>
<?php
}

function account_forgot_password(): void
{
render_header('Password help');
?>
<main id="main" class="page-main auth-main"><section class="auth-card"><span class="eyebrow">ACCOUNT SUPPORT</span><h1>Password reset</h1><p>Password reset emails need an SMTP provider. Email delivery is not configured in this first release, so reset links are not sent.</p><div class="form-alert">Configure an SMTP service before enabling password reset. No account information has been changed.</div><a class="button button-outline" href="login.php">Back to log in</a></section></main>
<?php render_footer(); ?>
<?php
}

function account_reset_password(): void
{
render_header('Reset password');
?>
<main id="main" class="page-main auth-main"><section class="auth-card"><span class="eyebrow">ACCOUNT SUPPORT</span><h1>Password reset unavailable</h1><p>Email-based password reset is not enabled until an SMTP provider and verified reset flow are configured.</p><a class="button button-outline" href="login.php">Back to log in</a></section></main>
<?php render_footer(); ?>
<?php
}

function account_logout(): void
{
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(405);
    exit('Method not allowed.');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: ' . app_config('app_url') . '/index.php');
exit;
}

function account_profile(): void
{
$user = require_login();
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif (isset($_POST['update_profile'])) {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid name and email address.';
        } else {
            try {
                $update = db()->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id');
                $update->execute(['name' => $name, 'email' => $email, 'id' => $user['id']]);
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;
                $user = current_user();
                $message = 'Profile updated.';
            } catch (PDOException $exception) {
                $error = $exception->getCode() === '23000' ? 'That email is already in use.' : 'The profile could not be updated.';
                if ($exception->getCode() !== '23000') {
                    log_app_error($exception, 'profile');
                }
            }
        }
    } elseif (isset($_POST['delete_account'])) {
        $statement = db()->prepare('SELECT password_hash FROM users WHERE id = :id');
        $statement->execute(['id' => $user['id']]);
        $hash = (string) $statement->fetchColumn();
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
            $error = 'Current password is incorrect.';
        } else {
            try {
                $files = db()->prepare('SELECT f.stored_path, j.result_path FROM files f LEFT JOIN jobs j ON j.file_id = f.id WHERE f.user_id = :user_id');
                $files->execute(['user_id' => $user['id']]);
                $paths = [];
                foreach ($files->fetchAll() as $row) {
                    foreach ([$row['stored_path'], $row['result_path']] as $path) {
                        if (is_string($path) && $path !== '') {
                            $paths[] = $path;
                        }
                    }
                }
                foreach (array_unique($paths) as $path) {
                    if (is_file($path) && !unlink($path)) {
                        throw new RuntimeException('An account file could not be removed.');
                    }
                }
                $pdo = db();
                $pdo->beginTransaction();
                $removeFiles = $pdo->prepare('DELETE FROM files WHERE user_id = :user_id');
                $removeFiles->execute(['user_id' => $user['id']]);
                $removeUser = $pdo->prepare('DELETE FROM users WHERE id = :user_id');
                $removeUser->execute(['user_id' => $user['id']]);
                $pdo->commit();
                $_SESSION = [];
                session_destroy();
                header('Location: ' . app_config('app_url') . '/index.php');
                exit;
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                log_app_error($exception, 'account-deletion');
                $error = 'The account could not be deleted. Please try again later.';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $statement = db()->prepare('SELECT password_hash FROM users WHERE id = :id');
        $statement->execute(['id' => $user['id']]);
        $hash = (string) $statement->fetchColumn();
        $newPassword = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 10) {
            $error = 'Choose a new password with at least 10 characters.';
        } else {
            $update = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $update->execute(['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $user['id']]);
            $message = 'Password changed.';
        }
    }
}
render_header('Profile');
?>
<main id="main" class="page-main"><div class="container narrow-container"><a class="back-link" href="dashboard.php">← Dashboard</a><section class="auth-card profile-card"><span class="eyebrow">ACCOUNT SETTINGS</span><h1>Your profile</h1>
    <?php if ($message): ?><div class="success-alert" role="status"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="update_profile" value="1"><label>Name<input name="name" required maxlength="120" value="<?= e($user['name']) ?>"></label><label>Email<input name="email" type="email" required maxlength="190" value="<?= e($user['email']) ?>"></label><button class="button" type="submit">Save profile</button></form>
    <hr><h2>Change password</h2><form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="change_password" value="1"><label>Current password<input name="current_password" type="password" autocomplete="current-password" required></label><label>New password<input name="new_password" type="password" minlength="10" autocomplete="new-password" required></label><button class="button button-outline" type="submit">Update password</button></form>
    <form method="post" class="delete-account-form" onsubmit="return confirm('This permanently deletes your account and associated temporary files. Continue?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="delete_account" value="1"><label>Confirm with your password<input name="current_password" type="password" autocomplete="current-password" required></label><button class="text-button danger-text" type="submit">Delete account</button></form>
</section></div></main>
<?php render_footer(); ?>
<?php
}

function account_dashboard(): void
{
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
<?php
}

function account_admin(): void
{
$user = require_login();
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    render_error_page(403); return;
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
<?php
}
