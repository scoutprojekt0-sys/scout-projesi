<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0777, true);
    }

    $pdo = new PDO('sqlite:' . $dataDir . '/app.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier TEXT NOT NULL UNIQUE,
            failed_count INTEGER NOT NULL DEFAULT 0,
            first_failed_at INTEGER,
            lock_until INTEGER
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS security_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT,
            ip_address TEXT NOT NULL,
            event_type TEXT NOT NULL,
            details TEXT,
            created_at TEXT NOT NULL,
            created_at_ts INTEGER
        )'
    );

    ensure_security_logs_timestamp_column($pdo);

    return $pdo;
}

function ensure_security_logs_timestamp_column(PDO $pdo): void
{
    $columns = $pdo->query("PRAGMA table_info('security_logs')")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'created_at_ts') {
            return;
        }
    }

    $pdo->exec('ALTER TABLE security_logs ADD COLUMN created_at_ts INTEGER');
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function password_errors(string $password, string $username = ''): array
{
    $errors = [];
    if (strlen($password) < 12) {
        $errors[] = 'Sifre en az 12 karakter olmali.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Sifre en az bir kucuk harf icermeli.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Sifre en az bir buyuk harf icermeli.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Sifre en az bir rakam icermeli.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Sifre en az bir ozel karakter icermeli.';
    }

    $common = ['password', '123456', 'qwerty', 'letmein', 'admin', 'welcome'];
    if (in_array(strtolower($password), $common, true)) {
        $errors[] = 'Sifre cok yaygin; baska bir sifre secin.';
    }

    if ($username !== '' && stripos($password, $username) !== false) {
        $errors[] = 'Sifre kullanici adini icermemeli.';
    }

    return $errors;
}

function client_ip(): string
{
    if (!empty($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }

    return 'unknown-ip';
}

function attempt_identifier(string $username): string
{
    return strtolower(trim($username)) . '|' . client_ip();
}

function get_attempt(string $identifier): ?array
{
    $stmt = db()->prepare('SELECT * FROM login_attempts WHERE identifier = :identifier');
    $stmt->execute(['identifier' => $identifier]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function save_attempt(string $identifier, int $failedCount, ?int $firstFailedAt, ?int $lockUntil): void
{
    $stmt = db()->prepare(
        'INSERT INTO login_attempts(identifier, failed_count, first_failed_at, lock_until)
         VALUES(:identifier, :failed_count, :first_failed_at, :lock_until)
         ON CONFLICT(identifier) DO UPDATE SET
           failed_count = excluded.failed_count,
           first_failed_at = excluded.first_failed_at,
           lock_until = excluded.lock_until'
    );

    $stmt->execute([
        'identifier' => $identifier,
        'failed_count' => $failedCount,
        'first_failed_at' => $firstFailedAt,
        'lock_until' => $lockUntil,
    ]);
}

function clear_attempt(string $identifier): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE identifier = :identifier');
    $stmt->execute(['identifier' => $identifier]);
}

function check_lock_state(string $username): array
{
    $identifier = attempt_identifier($username);
    $attempt = get_attempt($identifier);
    $now = time();

    if ($attempt && !empty($attempt['lock_until']) && (int) $attempt['lock_until'] > $now) {
        return [
            'locked' => true,
            'seconds_left' => (int) $attempt['lock_until'] - $now,
            'identifier' => $identifier,
        ];
    }

    return ['locked' => false, 'seconds_left' => 0, 'identifier' => $identifier];
}

function register_failed_login(string $username): array
{
    $identifier = attempt_identifier($username);
    $attempt = get_attempt($identifier);
    $now = time();
    $windowSeconds = 10 * 60;
    $maxAttempts = 5;

    if (!$attempt || empty($attempt['first_failed_at']) || ($now - (int) $attempt['first_failed_at']) > $windowSeconds) {
        $failedCount = 1;
        $firstFailedAt = $now;
    } else {
        $failedCount = ((int) $attempt['failed_count']) + 1;
        $firstFailedAt = (int) $attempt['first_failed_at'];
    }

    $lockUntil = null;
    if ($failedCount >= $maxAttempts) {
        $lockUntil = $now + $windowSeconds;
    }

    save_attempt($identifier, $failedCount, $firstFailedAt, $lockUntil);

    return [
        'failed_count' => $failedCount,
        'locked' => $lockUntil !== null,
        'seconds_left' => $lockUntil ? $lockUntil - $now : 0,
    ];
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, username, created_at FROM users WHERE id = :id');
    $stmt->execute(['id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function log_security_event(string $eventType, string $username = '', string $details = ''): void
{
    $stmt = db()->prepare(
        'INSERT INTO security_logs(username, ip_address, event_type, details, created_at, created_at_ts)
         VALUES(:username, :ip_address, :event_type, :details, :created_at, :created_at_ts)'
    );

    $stmt->execute([
        'username' => $username !== '' ? $username : null,
        'ip_address' => client_ip(),
        'event_type' => $eventType,
        'details' => $details !== '' ? $details : null,
        'created_at' => date('c'),
        'created_at_ts' => time(),
    ]);
}

function recent_security_logs(int $limit = 20): array
{
    $safeLimit = max(1, min(200, $limit));
    $stmt = db()->query(
        'SELECT id, username, ip_address, event_type, details, created_at, created_at_ts
         FROM security_logs
         ORDER BY id DESC
         LIMIT ' . $safeLimit
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function parse_datetime_local_to_ts(?string $value): ?int
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return $timestamp;
}

function get_security_logs(array $filters = [], int $limit = 50): array
{
    $safeLimit = max(1, min(500, $limit));
    $conditions = [];
    $params = [];

    if (!empty($filters['event_type'])) {
        $conditions[] = 'event_type = :event_type';
        $params['event_type'] = (string) $filters['event_type'];
    }
    if (!empty($filters['username'])) {
        $conditions[] = 'username = :username';
        $params['username'] = (string) $filters['username'];
    }
    if (isset($filters['from_ts']) && $filters['from_ts'] !== null) {
        $conditions[] = 'COALESCE(created_at_ts, 0) >= :from_ts';
        $params['from_ts'] = (int) $filters['from_ts'];
    }
    if (isset($filters['to_ts']) && $filters['to_ts'] !== null) {
        $conditions[] = 'COALESCE(created_at_ts, 0) <= :to_ts';
        $params['to_ts'] = (int) $filters['to_ts'];
    }

    $sql = 'SELECT id, username, ip_address, event_type, details, created_at, created_at_ts FROM security_logs';
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . $safeLimit;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function get_security_metrics(array $filters = []): array
{
    $conditions = [];
    $params = [];

    if (isset($filters['from_ts']) && $filters['from_ts'] !== null) {
        $conditions[] = 'COALESCE(created_at_ts, 0) >= :from_ts';
        $params['from_ts'] = (int) $filters['from_ts'];
    }
    if (isset($filters['to_ts']) && $filters['to_ts'] !== null) {
        $conditions[] = 'COALESCE(created_at_ts, 0) <= :to_ts';
        $params['to_ts'] = (int) $filters['to_ts'];
    }

    $where = $conditions ? (' WHERE ' . implode(' AND ', $conditions)) : '';

    $sql = 'SELECT
                COUNT(*) AS total_events,
                SUM(CASE WHEN event_type = "login_failed" THEN 1 ELSE 0 END) AS failed_logins,
                SUM(CASE WHEN event_type IN ("login_locked", "login_blocked") THEN 1 ELSE 0 END) AS lock_events,
                SUM(CASE WHEN event_type = "login_success" THEN 1 ELSE 0 END) AS successful_logins
            FROM security_logs' . $where;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total_events' => (int) ($row['total_events'] ?? 0),
        'failed_logins' => (int) ($row['failed_logins'] ?? 0),
        'lock_events' => (int) ($row['lock_events'] ?? 0),
        'successful_logins' => (int) ($row['successful_logins'] ?? 0),
    ];
}
