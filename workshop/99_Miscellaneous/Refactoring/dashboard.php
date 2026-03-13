<?php
require_once 'config.php';
require_once SITE_PATH . '/includes/session.php';
require_once SITE_PATH . '/includes/connectDB.php';

require_any_role($session, array('admin', 'editor'));

$failedLoginCount = 0;
$recentContentEvents = array();
$activeUsers = array();
$activeWindowMinutes = 15;

$failQuery = "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'login_fail' AND created_at >= (NOW() - INTERVAL 24 HOUR)";
$failStatement = $databaseConnection->prepare($failQuery);
$failStatement->execute();
$failStatement->bind_result($failedLoginCountDb);
if ($failStatement->fetch()) {
    $failedLoginCount = (int) $failedLoginCountDb;
}
$failStatement->close();

$contentQuery = "SELECT event_type, actor_username, target_id, created_at
                 FROM audit_logs
                 WHERE event_type IN ('add', 'edit', 'delete')
                 ORDER BY created_at DESC
                 LIMIT 10";
$contentStatement = $databaseConnection->prepare($contentQuery);
$contentStatement->execute();
$contentStatement->bind_result($eventType, $actorUsername, $targetId, $createdAt);
while ($contentStatement->fetch()) {
    $recentContentEvents[] = array(
        'event_type' => (string) $eventType,
        'actor_username' => $actorUsername === null ? '-' : (string) $actorUsername,
        'target_id' => $targetId === null ? '-' : (int) $targetId,
        'created_at' => (string) $createdAt,
    );
}
$contentStatement->close();

$activeQuery = "SELECT login_events.actor_user_id, COALESCE(login_events.actor_username, '') AS actor_username, MAX(login_events.created_at) AS last_login_at
                FROM audit_logs login_events
                LEFT JOIN audit_logs logout_events
                  ON logout_events.actor_user_id = login_events.actor_user_id
                 AND logout_events.event_type = 'logout'
                 AND logout_events.created_at >= login_events.created_at
                WHERE login_events.event_type = 'login_success'
                  AND login_events.created_at >= (NOW() - INTERVAL ? MINUTE)
                  AND logout_events.id IS NULL
                GROUP BY login_events.actor_user_id, login_events.actor_username
                ORDER BY last_login_at DESC
                LIMIT 25";
$activeStatement = $databaseConnection->prepare($activeQuery);
$activeStatement->bind_param('i', $activeWindowMinutes);
$activeStatement->execute();
$activeStatement->bind_result($activeUserId, $activeUsername, $lastLoginAt);
while ($activeStatement->fetch()) {
    $activeUsers[] = array(
        'user_id' => (int) $activeUserId,
        'username' => $activeUsername === '' ? ('user#' . (int) $activeUserId) : (string) $activeUsername,
        'last_login_at' => (string) $lastLoginAt,
    );
}
$activeStatement->close();

include SITE_PATH . '/includes/header.php';
?>
<div id="main">
    <h2>Dashboard</h2>

    <p class="message-info">Son 24 saatte basarisiz login: <strong><?php echo (int) $failedLoginCount; ?></strong></p>

    <h3>Son Icerik Degisiklikleri</h3>
    <?php if (empty($recentContentEvents)) { ?>
        <p>Kayit bulunamadi.</p>
    <?php } else { ?>
        <table class="audit-table">
            <thead>
            <tr>
                <th>Olay</th>
                <th>Kullanici</th>
                <th>Sayfa ID</th>
                <th>Zaman</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($recentContentEvents as $event) { ?>
                <tr>
                    <td><?php echo e($event['event_type']); ?></td>
                    <td><?php echo e($event['actor_username']); ?></td>
                    <td><?php echo e($event['target_id']); ?></td>
                    <td><?php echo e($event['created_at']); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    <?php } ?>

    <h3>Aktif Kullanici Ozeti (Son <?php echo (int) $activeWindowMinutes; ?> dk)</h3>
    <p><strong><?php echo count($activeUsers); ?></strong> aktif kullanici</p>
    <?php if (!empty($activeUsers)) { ?>
        <ul>
            <?php foreach ($activeUsers as $activeUser) { ?>
                <li><?php echo e($activeUser['username']); ?> (son login: <?php echo e($activeUser['last_login_at']); ?>)</li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>
</div>
<?php include SITE_PATH . '/includes/footer.php'; ?>
