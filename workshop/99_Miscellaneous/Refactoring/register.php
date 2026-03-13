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
        $usernameLookupQuery = 'SELECT id FROM users WHERE username = ? LIMIT 1';
        $usernameLookupStatement = $databaseConnection->prepare($usernameLookupQuery);
        if (!$usernameLookupStatement) {
            app_fail('Veritabani islemi tamamlanamadi.', 'Database prepare failed: ' . $databaseConnection->error);
        }
        $usernameLookupStatement->bind_param('s', $username);
        $usernameLookupStatement->execute();
        $usernameLookupStatement->store_result();
        if ($usernameLookupStatement->num_rows > 0) {
            $errors[] = 'Bu kullanici adi zaten kayitli.';
        }
        $usernameLookupStatement->close();
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $query = 'INSERT INTO users (username, password) VALUES (?, ?)';
        $statement = $databaseConnection->prepare($query);
        $statement->bind_param('ss', $username, $hashedPassword);
        $statement->execute();

        if ($statement->error) {
            if ($statement->errno === 1062) {
                $errors[] = 'Bu kullanici adi zaten kayitli.';
            } else {
                app_fail('Veritabani islemi tamamlanamadi.', 'Database query failed: ' . $statement->error);
            }
        }

        if (empty($errors) && $statement->affected_rows === 1) {
            $userId = $statement->insert_id;

            $roleId = 3;
            $roleLookupQuery = "SELECT id FROM roles WHERE name = 'viewer' LIMIT 1";
            $roleLookupStatement = $databaseConnection->prepare($roleLookupQuery);
            $roleLookupStatement->execute();
            $roleLookupStatement->bind_result($resolvedRoleId);
            if ($roleLookupStatement->fetch()) {
                $roleId = (int) $resolvedRoleId;
            }
            $roleLookupStatement->close();

            $addToUserRoleQuery = 'INSERT INTO users_in_roles (user_id, role_id) VALUES (?, ?)';
            $addUserToUserRoleStatement = $databaseConnection->prepare($addToUserRoleQuery);
            $addUserToUserRoleStatement->bind_param('ii', $userId, $roleId);
            $addUserToUserRoleStatement->execute();
            if ($addUserToUserRoleStatement->error) {
                app_fail('Rol atamasi yapilamadi.', 'Role assign query failed: ' . $addUserToUserRoleStatement->error);
            }

            session_regenerate_id(true);
            $session->set('userid', (int) $userId);
            $session->set('username', $username);
            login_rate_limit_reset($username);
            app_redirect_route('home');
        }

        if (empty($errors)) {
            $errors[] = 'Kayit olusturulamadi.';
        }
    }
}

include SITE_PATH . '/includes/header.php';
?>
<div id="main">
    <h2>Register an account</h2>

    <?php render_error_summary($errors); ?>

    <form action="<?php echo e(app_route_url('register')); ?>" method="post" novalidate>
        <?php echo csrf_field(); ?>
        <fieldset>
            <legend>Register an account</legend>
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
