<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
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
