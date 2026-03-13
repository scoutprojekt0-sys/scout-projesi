<?php
require_once 'config.php';
require_once SITE_PATH . '/includes/validation.php';
require_once SITE_PATH . '/includes/session.php';
require_once SITE_PATH . '/includes/connectDB.php';

require_auth($session);

$errors = array();
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_fail();

    $currentPassword = validate_required_string('current_password', 'Mevcut sifre', 255, $errors);
    $newPassword = validate_required_string('new_password', 'Yeni sifre', 255, $errors);
    $newPasswordAgain = validate_required_string('new_password_again', 'Yeni sifre tekrar', 255, $errors);

    if (empty($errors)) {
        if (mb_strlen($newPassword) < 10) {
            $errors[] = 'Yeni sifre en az 10 karakter olmalidir.';
        }
        if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
            $errors[] = 'Yeni sifre en az bir buyuk harf, bir kucuk harf ve bir rakam icermelidir.';
        }
        if (!hash_equals($newPassword, $newPasswordAgain)) {
            $errors[] = 'Yeni sifre tekrar alani eslesmiyor.';
        }
    }

    if (empty($errors)) {
        $query = 'SELECT id, password FROM users WHERE id = ? LIMIT 1';
        $statement = $databaseConnection->prepare($query);
        $userId = (int) $session->get('userid');
        $statement->bind_param('i', $userId);
        $statement->execute();
        $statement->store_result();

        if ($statement->error) {
            app_fail('Veritabani islemi tamamlanamadi.', 'Database query failed: ' . $statement->error);
        }

        if ($statement->num_rows !== 1) {
            $errors[] = 'Kullanici bulunamadi.';
        } else {
            $statement->bind_result($dbUserId, $storedHash);
            $statement->fetch();

            $verify = normalize_password_hash((string) $storedHash, $currentPassword);
            if (!$verify[0]) {
                $errors[] = 'Mevcut sifre hatali.';
            } else {
                if (hash_equals($currentPassword, $newPassword)) {
                    $errors[] = 'Yeni sifre mevcut sifre ile ayni olamaz.';
                }
            }
        }
    }

    if (empty($errors)) {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateQuery = 'UPDATE users SET password = ? WHERE id = ?';
        $updateStatement = $databaseConnection->prepare($updateQuery);
        $userId = (int) $session->get('userid');
        $updateStatement->bind_param('si', $newHash, $userId);
        $updateStatement->execute();

        if ($updateStatement->error) {
            app_fail('Sifre guncellenemedi.', 'Password update query failed: ' . $updateStatement->error);
        }

        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $successMessage = 'Sifreniz basariyla guncellendi.';
        app_log('info', 'Password changed', array('user_id' => $userId));
        audit_log('password_change', 'user', $userId);
    }
}

include SITE_PATH . '/includes/header.php';
?>
<div id="main">
    <h2>Password</h2>

    <?php render_error_summary($errors); ?>
    <?php if ($successMessage !== '') { ?>
        <p class="message-success"><?php echo e($successMessage); ?></p>
    <?php } ?>

    <form action="<?php echo e(app_route_url('password.change')); ?>" method="post" novalidate>
        <?php echo csrf_field(); ?>
        <fieldset>
            <legend>Password Change</legend>
            <ol>
                <li>
                    <label for="current_password">Mevcut sifre:</label>
                    <input type="password" name="current_password" id="current_password" maxlength="255" required aria-required="true"/>
                </li>
                <li>
                    <label for="new_password">Yeni sifre:</label>
                    <input type="password" name="new_password" id="new_password" maxlength="255" required aria-required="true"/>
                </li>
                <li>
                    <label for="new_password_again">Yeni sifre (tekrar):</label>
                    <input type="password" name="new_password_again" id="new_password_again" maxlength="255" required aria-required="true"/>
                </li>
            </ol>
            <input type="submit" value="Guncelle"/>
            <p><a href="<?php echo e(app_route_url('home')); ?>">Cancel</a></p>
        </fieldset>
    </form>
</div>
</div>
<?php include SITE_PATH . '/includes/footer.php'; ?>
