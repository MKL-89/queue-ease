<?php
/**
 * QueueEase - Customer self-registration
 * File: register.php
 */
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect(dashboard_for_role(user_role()));
}

$errors = [];

if (is_post()) {
    require_csrf();
    $fullName = post('full_name');
    $email    = strtolower(post('email'));
    $phone    = preg_replace('/\s+/', '', post('phone'));
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!valid_name($fullName)) {
        $errors['full_name'] = 'Enter your full name (letters only, 2-100 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!valid_phone($phone)) {
        $errors['phone'] = 'Enter a valid phone number (9-15 digits).';
    }
    if (!valid_password($password)) {
        $errors['password'] = password_rule_text();
    } elseif ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            $errors['email'] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'customer')")
                ->execute([$fullName, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO customers (user_id, full_name, phone, email, is_guest) VALUES (?, ?, ?, ?, 0)')
                ->execute([$userId, $fullName, $phone, $email]);
            $pdo->commit();

            log_activity('customer_registered', 'New customer registered: ' . $fullName, $userId);
            set_flash('success', 'Registration successful. You can now log in.');
            redirect('login.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Registration failed: ' . $ex->getMessage());
            $errors['general'] = 'Registration failed. Please try again.';
        }
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/public_header.php';

?>
<section class="auth-split">
    <?php $asideTitle = 'Join in under a minute.'; $asideText = 'Create a free account to take tickets online, get alerts when you are called and keep a history of your visits.'; require __DIR__ . '/includes/auth_aside.php'; ?>
    <div class="card auth-card wide">
        <div class="text-center mb-2">
            <div class="empty-icon"><?= icon('user-plus') ?></div>
            <h2>Create your account</h2>
            <p class="muted">Register to take tickets online and keep a history of your visits.</p>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error"><span><?= e($errors['general']) ?></span></div>
        <?php elseif ($errors): ?>
            <div class="alert alert-error"><span>Please correct the highlighted fields.</span></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="full_name">Full name <span class="req">*</span></label>
                <input type="text" id="full_name" name="full_name" class="form-control<?= invalid($errors, 'full_name') ?>" value="<?= old('full_name') ?>" maxlength="100" required>
                <?= field_error($errors, 'full_name') ?>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email address <span class="req">*</span></label>
                    <input type="email" id="email" name="email" class="form-control<?= invalid($errors, 'email') ?>" value="<?= old('email') ?>" maxlength="150" required autocomplete="email">
                    <?= field_error($errors, 'email') ?>
                </div>
                <div class="form-group">
                    <label for="phone">Phone number <span class="req">*</span></label>
                    <input type="tel" id="phone" name="phone" class="form-control<?= invalid($errors, 'phone') ?>" value="<?= old('phone') ?>" maxlength="16" placeholder="0712345678" required>
                    <?= field_error($errors, 'phone') ?>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span class="req">*</span></label>
                    <input type="password" id="password" name="password" class="form-control<?= invalid($errors, 'password') ?>" required autocomplete="new-password">
                    <?= field_error($errors, 'password') ?: '<div class="form-hint">' . e(password_rule_text()) . '</div>' ?>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm password <span class="req">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control<?= invalid($errors, 'confirm_password') ?>" required autocomplete="new-password">
                    <?= field_error($errors, 'confirm_password') ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Create account</button>
        </form>
        <p class="auth-footer">Already registered? <a href="<?= url('login.php') ?>">Log in</a></p>
    </div>
</section>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
