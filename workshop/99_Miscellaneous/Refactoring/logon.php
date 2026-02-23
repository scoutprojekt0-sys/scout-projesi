<?php
require_once 'config.php';
require_once SITE_PATH . '/includes/validation.php';
require_once SITE_PATH . '/includes/session.php';
require_once SITE_PATH . '/includes/connectDB.php';

$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_fail();

    $username = validate_required_string('username', 'Kullanici adi', 50, $errors);
    $password = validate_required_string('password', 'Sifre', 255, $errors);

    if (empty($errors)) {
        if (login_rate_limit_is_blocked($username)) {
            $errors[] = 'Cok fazla hatali deneme. Lutfen daha sonra tekrar deneyin.';
            app_log('warning', 'Blocked login attempt', array('username' => $username, 'ip' => request_client_ip()));
            audit_log('login_fail', 'user', null, array('username' => $username, 'reason' => 'rate_limited'));
        } else {
            $query = 'SELECT id, username, password FROM users WHERE username = ? LIMIT 1';
            $statement = $databaseConnection->prepare($query);
            $statement->bind_param('s', $username);
            $statement->execute();
            $statement->store_result();

            if ($statement->error) {
                app_fail('Veritabani islemi tamamlanamadi.', 'Database query failed: ' . $statement->error);
            }

            $authenticated = false;
            if ($statement->num_rows === 1) {
                $statement->bind_result($userId, $fetchedUsername, $storedHash);
                $statement->fetch();

                $result = normalize_password_hash((string) $storedHash, $password);
                $authenticated = (bool) $result[0];
                $newHash = $result[1];

                if ($authenticated && $newHash !== null) {
                    $updateQuery = 'UPDATE users SET password = ? WHERE id = ?';
                    $updateStatement = $databaseConnection->prepare($updateQuery);
                    $updateStatement->bind_param('si', $newHash, $userId);
                    $updateStatement->execute();
                }
            }

            if ($authenticated) {
                session_regenerate_id(true);
                $session->set('userid', (int) $userId);
                $session->set('username', $fetchedUsername);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                login_rate_limit_reset($username);
                unset($_SESSION['role_cache']);
                audit_log('login_success', 'user', (int) $userId, array('username' => $fetchedUsername));
                app_redirect_route('home');
            }

            login_rate_limit_register_failure($username);
            audit_log('login_fail', 'user', null, array('username' => $username, 'reason' => 'invalid_credentials'));
            $errors[] = 'Kullanici adi veya sifre hatali.';
        }
    }
}

include SITE_PATH . '/includes/header.php';
?>
<div id="main">
    <h2>Log on</h2>

    <?php render_error_summary($errors); ?>

    <form action="<?php echo e(app_route_url('login')); ?>" method="post" novalidate>
        <?php echo csrf_field(); ?>
        <fieldset>
            <legend>Log on</legend>
            <ol>
                <li>
                    <label for="username">Username:</label>
                    <input type="text" name="username" value="<?php echo e(post_value('username')); ?>" id="username" maxlength="50" required aria-required="true"/>
                </li>
                <li>
                    <label for="password">Password:</label>
                    <input type="password" name="password" value="" id="password" maxlength="255" required aria-required="true"/>
                </li>
            </ol>
            <input type="submit" name="submit" value="Submit"/>

            <p>
                <a href="<?php echo e(app_route_url('home')); ?>">Cancel</a>
            </p>
        </fieldset>
    </form>
</div>
</div>
<?php include SITE_PATH . '/includes/footer.php'; ?>
