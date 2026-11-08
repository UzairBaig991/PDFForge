<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
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
