<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
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
