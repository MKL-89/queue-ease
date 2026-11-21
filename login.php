<?php
/**
 * QueueEase - Login (all roles)
 * File: login.php
 */
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect(dashboard_for_role(user_role()));
}

$errors = [];
$email  = '';

if (is_post()) {
    require_csrf();
    $email    = strtolower(post('email'));
    $password = $_POST['password'] ?? '';

    if (($wait = login_locked_seconds()) > 0) {
        $errors[] = 'Too many failed attempts. Try again in ' . ceil($wait / 60) . ' minute(s).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $errors[] = 'Enter a valid email address and your password.';
    } else {
        $stmt = db()->prepare('SELECT id, full_name, email, password_hash, role, is_active FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ((int) $user['is_active'] !== 1) {
                $errors[] = 'Your account has been deactivated. Please contact the administrator.';
                log_activity('login_blocked', 'Login attempt on inactive account ' . $email, (int) $user['id']);
            } else {
                // Upgrade the hash automatically if PHP's default algorithm changes
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                }
                clear_failed_logins();
                login_user($user);
                db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
                log_activity('login', $user['full_name'] . ' logged in');
                set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect(dashboard_for_role($user['role']));
            }
        } else {
            register_failed_login();
            log_activity('login_failed', 'Failed login for ' . $email, $user ? (int) $user['id'] : null);
            $errors[] = 'Incorrect email or password.';
        }
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/public_header.php';
?>
<section class="auth-split">
    <?php require __DIR__ . '/includes/auth_aside.php'; ?>
    <div class="card auth-card">
        <div class="text-center mb-2">
            <div class="empty-icon"><?= icon('lock') ?></div>
            <h2>Welcome back</h2>
            <p class="muted">Log in to your <?= e(APP_NAME) ?> account</p>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-error"><span><?= e(implode(' ', $errors)) ?></span></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Log in</button>
        </form>

        <p class="auth-footer">
            New customer? <a href="<?= url('register.php') ?>">Create an account</a><br>
            or <a href="<?= url('queue/guest_ticket.php') ?>">get a ticket without registering</a>
        </p>

        <div class="demo-accounts">
            <strong>Demo accounts</strong> (from the installation data &mdash; change after first use):<br>
            Admin: <code>admin@queueease.local</code> / <code>Admin@123</code><br>
            Staff: <code>grace@queueease.local</code> / <code>Staff@123</code><br>
            Customer: <code>customer@queueease.local</code> / <code>Customer@123</code>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
