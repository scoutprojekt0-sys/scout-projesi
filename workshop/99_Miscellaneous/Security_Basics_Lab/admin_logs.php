<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/functions.php';

$user = current_user();
if (!$user) {
    header('Location: index.php');
    exit;
}

$eventType = trim((string) ($_GET['event_type'] ?? ''));
$username = trim((string) ($_GET['username'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));

$fromTs = parse_datetime_local_to_ts($from !== '' ? $from : null);
$toTs = parse_datetime_local_to_ts($to !== '' ? $to : null);
if ($toTs !== null) {
    $toTs += 59;
}

$filters = [
    'event_type' => $eventType,
    'username' => $username,
    'from_ts' => $fromTs,
    'to_ts' => $toTs,
];

$metrics = get_security_metrics($filters);
$logs = get_security_logs($filters, 100);

$knownEvents = [
    'login_failed',
    'login_success',
    'login_locked',
    'login_blocked',
    'register_success',
    'register_weak_password',
    'register_existing_user',
    'csrf_failure',
    'logout',
];
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Logs</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #f6f8fa; }
        .wrap { max-width: 1100px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 1.25rem; }
        .top { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .metrics { display: grid; grid-template-columns: repeat(4, 1fr); gap: .75rem; margin: 1rem 0; }
        .metric { border: 1px solid #d0d7de; border-radius: 8px; padding: .75rem; background: #f6f8fa; }
        .metric strong { display: block; font-size: 1.1rem; margin-top: .25rem; }
        form { border: 1px solid #d0d7de; border-radius: 8px; padding: .75rem; margin: 1rem 0; }
        .filters { display: grid; grid-template-columns: repeat(4, 1fr); gap: .6rem; }
        label { font-size: .9rem; display: block; }
        input, select { width: 100%; box-sizing: border-box; margin-top: .25rem; padding: .45rem; }
        button { margin-top: .7rem; padding: .5rem .75rem; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { border: 1px solid #d0d7de; padding: .45rem; text-align: left; vertical-align: top; }
        th { background: #f6f8fa; }
        .table-wrap { overflow-x: auto; }
        code { background: #f0f3f6; padding: 2px 6px; border-radius: 4px; }
        .actions { display: flex; gap: .75rem; align-items: center; }
        @media (max-width: 900px) {
            .metrics { grid-template-columns: repeat(2, 1fr); }
            .filters { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            .metrics, .filters { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Admin Logs</h1>
            <p>Giris yapan kullanici: <strong><?= h($user['username']) ?></strong></p>
        </div>
        <div class="actions">
            <a href="index.php">Ana sayfa</a>
            <a href="index.php?logout=1">Cikis yap</a>
        </div>
    </div>

    <div class="metrics">
        <div class="metric">Toplam event<strong><?= h((string) $metrics['total_events']) ?></strong></div>
        <div class="metric">Basarisiz login<strong><?= h((string) $metrics['failed_logins']) ?></strong></div>
        <div class="metric">Kilit eventleri<strong><?= h((string) $metrics['lock_events']) ?></strong></div>
        <div class="metric">Basarili login<strong><?= h((string) $metrics['successful_logins']) ?></strong></div>
    </div>

    <form method="get">
        <div class="filters">
            <label>Event type
                <select name="event_type">
                    <option value="">Tum eventler</option>
                    <?php foreach ($knownEvents as $evt): ?>
                        <option value="<?= h($evt) ?>" <?= $eventType === $evt ? 'selected' : '' ?>><?= h($evt) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Username
                <input type="text" name="username" value="<?= h($username) ?>">
            </label>
            <label>From
                <input type="datetime-local" name="from" value="<?= h($from) ?>">
            </label>
            <label>To
                <input type="datetime-local" name="to" value="<?= h($to) ?>">
            </label>
        </div>
        <button type="submit">Filtrele</button>
        <a href="admin_logs.php">Sifirla</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Zaman</th>
                <th>Event</th>
                <th>Kullanici</th>
                <th>IP</th>
                <th>Detay</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="5">Filtreye uygun kayit yok.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $row): ?>
                    <tr>
                        <td><?= h($row['created_at']) ?></td>
                        <td><code><?= h($row['event_type']) ?></code></td>
                        <td><?= h((string) ($row['username'] ?? '-')) ?></td>
                        <td><?= h($row['ip_address']) ?></td>
                        <td><?= h((string) ($row['details'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
