<?php

class Database
{
}

function prep_DB_content()
{
    global $databaseConnection;
    $adminRoleId = 1;

    create_tables($databaseConnection);
    ensure_users_username_unique_constraint($databaseConnection);
    create_roles($databaseConnection, $adminRoleId);
    create_admin($databaseConnection, $adminRoleId);
    backfill_viewer_role($databaseConnection);
    create_homepage($databaseConnection);
}

function create_tables($databaseConnection)
{
    $databaseConnection->query('CREATE TABLE IF NOT EXISTS users (
        id INT NOT NULL AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL,
        password VARCHAR(255) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_users_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $databaseConnection->query('CREATE TABLE IF NOT EXISTS roles (
        id INT NOT NULL,
        name VARCHAR(50) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_roles_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $databaseConnection->query('CREATE TABLE IF NOT EXISTS users_in_roles (
        id INT NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        role_id INT NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_users_in_roles_user_role (user_id, role_id),
        KEY idx_users_in_roles_user_id (user_id),
        KEY idx_users_in_roles_role_id (role_id),
        CONSTRAINT fk_users_in_roles_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_users_in_roles_role_id FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $databaseConnection->query('CREATE TABLE IF NOT EXISTS pages (
        id INT NOT NULL AUTO_INCREMENT,
        menulabel VARCHAR(50) NOT NULL,
        content TEXT NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_pages_menulabel (menulabel)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $databaseConnection->query('CREATE TABLE IF NOT EXISTS audit_logs (
        id BIGINT NOT NULL AUTO_INCREMENT,
        event_type VARCHAR(50) NOT NULL,
        actor_user_id INT NULL,
        actor_username VARCHAR(50) NULL,
        target_type VARCHAR(50) NULL,
        target_id INT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent VARCHAR(255) NOT NULL DEFAULT "",
        metadata_json TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_audit_created_at (created_at),
        KEY idx_audit_event_type (event_type),
        KEY idx_audit_actor_user_id (actor_user_id),
        KEY idx_audit_target (target_type, target_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function create_roles($databaseConnection, $adminRoleId)
{
    $databaseConnection->query("UPDATE roles SET name = 'viewer' WHERE name = 'user'");
    $databaseConnection->query("INSERT IGNORE INTO roles (id, name) VALUES ($adminRoleId, 'admin')");
    $databaseConnection->query("INSERT IGNORE INTO roles (id, name) VALUES (2, 'viewer')");
    $databaseConnection->query("INSERT IGNORE INTO roles (id, name) VALUES (4, 'editor')");
}

function create_admin($databaseConnection, $adminRoleId)
{
    $defaultAdminUsername = DEFAULT_ADMIN_USERNAME;
    $defaultAdminPassword = DEFAULT_ADMIN_PASSWORD;

    $queryCheckAdminExists = 'SELECT id FROM users WHERE username = ? LIMIT 1';
    $statementCheckAdminExists = $databaseConnection->prepare($queryCheckAdminExists);
    $statementCheckAdminExists->bind_param('s', $defaultAdminUsername);
    $statementCheckAdminExists->execute();
    $statementCheckAdminExists->store_result();

    if ($statementCheckAdminExists->num_rows === 0) {
        $queryInsertAdmin = 'INSERT INTO users (username, password) VALUES (?, ?)';
        $statementInsertAdmin = $databaseConnection->prepare($queryInsertAdmin);
        $adminPasswordHash = password_hash($defaultAdminPassword, PASSWORD_DEFAULT);
        $statementInsertAdmin->bind_param('ss', $defaultAdminUsername, $adminPasswordHash);
        $statementInsertAdmin->execute();

        $adminUserId = $statementInsertAdmin->insert_id;
        $queryAddAdminToRole = 'INSERT INTO users_in_roles (user_id, role_id) VALUES (?, ?)';
        $statementAddAdminToRole = $databaseConnection->prepare($queryAddAdminToRole);
        $statementAddAdminToRole->bind_param('ii', $adminUserId, $adminRoleId);
        $statementAddAdminToRole->execute();
        $statementAddAdminToRole->close();
    }
}

function backfill_viewer_role($databaseConnection)
{
    $viewerRoleId = null;
    $roleStatement = $databaseConnection->prepare("SELECT id FROM roles WHERE name = 'viewer' LIMIT 1");
    $roleStatement->execute();
    $roleStatement->bind_result($roleId);
    if ($roleStatement->fetch()) {
        $viewerRoleId = (int) $roleId;
    }
    $roleStatement->close();

    if ($viewerRoleId === null) {
        return;
    }

    $query = 'INSERT INTO users_in_roles (user_id, role_id)
              SELECT U.id, ?
              FROM users U
              LEFT JOIN users_in_roles UIR ON UIR.user_id = U.id
              WHERE UIR.user_id IS NULL';
    $statement = $databaseConnection->prepare($query);
    $statement->bind_param('i', $viewerRoleId);
    $statement->execute();
    $statement->close();
}

function create_homepage($databaseConnection)
{
    $homepageId = 1;
    $homepageTitle = 'Home';
    $homepageContents = '<ol class="round">
    <li class="one">
        <h5>Login as admin </h5>
The site admin username and password are both &quot;admin&quot;.
    </li>
    <li class="two">
        <h5>Customize your site</h5>
After you login, you can add, delete, and modify web pages.
     </li>
    <li class="asterisk">
        <div class="visit">
To learn more about PhpStorm, visit <a href="http://www.jetbrains.com/phpstorm" title="PhpStorm website">jetbrains.com/phpstorm</a>.
        </div>
     </li>
</ol>';

    $queryCheckHomepageExists = 'SELECT id FROM pages WHERE id = ? LIMIT 1';
    $statementCheckHomepageExists = $databaseConnection->prepare($queryCheckHomepageExists);
    $statementCheckHomepageExists->bind_param('i', $homepageId);
    $statementCheckHomepageExists->execute();
    $statementCheckHomepageExists->store_result();

    if ($statementCheckHomepageExists->num_rows === 0) {
        $queryInsertPage = 'INSERT INTO pages (id, menulabel, content) VALUES (?, ?, ?)';
        $statementInsertPage = $databaseConnection->prepare($queryInsertPage);
        $statementInsertPage->bind_param('iss', $homepageId, $homepageTitle, $homepageContents);
        $statementInsertPage->execute();
    }
}

function ensure_users_username_unique_constraint($databaseConnection)
{
    $checkDuplicateQuery = 'SELECT username FROM users GROUP BY username HAVING COUNT(*) > 1 LIMIT 1';
    $checkDuplicateResult = $databaseConnection->query($checkDuplicateQuery);
    if ($checkDuplicateResult && $checkDuplicateResult->num_rows > 0) {
        app_log('warning', 'Cannot add unique index on users.username because duplicate usernames exist');
        return;
    }

    $checkIndexQuery = "SHOW INDEX FROM users WHERE Key_name = 'uq_users_username'";
    $checkIndexResult = $databaseConnection->query($checkIndexQuery);
    if (!$checkIndexResult) {
        app_log('warning', 'Unable to verify users.username unique index', array('db_error' => $databaseConnection->error));
        return;
    }

    if ($checkIndexResult->num_rows > 0) {
        return;
    }

    $databaseConnection->query("ALTER TABLE users ADD UNIQUE KEY uq_users_username (username)");
    if ($databaseConnection->error) {
        app_log('warning', 'Failed to add users.username unique index', array('db_error' => $databaseConnection->error));
    }
}
