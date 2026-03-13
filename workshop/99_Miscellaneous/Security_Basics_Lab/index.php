<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/functions.php';

$messages = [];
$errors = [];

if (isset($_GET['logout'])) {
    $logoutUser = current_user();
    if ($logoutUser) {
        log_security_event('logout', $logoutUser['username'], 'Kullanici cikis yapti');
    }
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Gecersiz istek (CSRF kontrolu basarisiz).';
        log_security_event('csrf_failure', '', 'CSRF token dogrulanamadi');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'register') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($username === '' || strlen($username) < 3) {
                $errors[] = 'Kullanici adi en az 3 karakter olmali.';
            }

            $passwordIssues = password_errors($password, $username);
            if ($passwordIssues) {
                $errors = array_merge($errors, $passwordIssues);
                log_security_event('register_weak_password', $username, 'Sifre politikasi gecmedi');
            }

            if (!$errors) {
                $stmt = db()->prepare('SELECT id FROM users WHERE username = :username');
                $stmt->execute(['username' => $username]);
                if ($stmt->fetch()) {
                    $errors[] = 'Bu kullanici adi zaten kayitli.';
                    log_security_event('register_existing_user', $username, 'Ayni ad ile ikinci kayit denemesi');
                } else {
                    $insert = db()->prepare(
                        'INSERT INTO users(username, password_hash, created_at) VALUES(:username, :password_hash, :created_at)'
                    );
                    $insert->execute([
                        'username' => $username,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'created_at' => date('c'),
                    ]);
                    $messages[] = 'Kayit basarili. Simdi giris yapabilirsin.';
                    log_security_event('register_success', $username, 'Yeni kullanici kaydi olusturuldu');
                }
            }
        }

        if ($action === 'login') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($username === '' || $password === '') {
                $errors[] = 'Kullanici adi ve sifre zorunlu.';
            } else {
                $lock = check_lock_state($username);
                if ($lock['locked']) {
                    $errors[] = 'Hesap gecici kilitli. Kalan sure: ' . $lock['seconds_left'] . ' sn';
                    log_security_event('login_blocked', $username, 'Rate limit nedeniyle giris engellendi');
                } else {
                    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = :username');
                    $stmt->execute(['username' => $username]);
                    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($userRow && password_verify($password, $userRow['password_hash'])) {
                        clear_attempt($lock['identifier']);
                        $_SESSION['user_id'] = (int) $userRow['id'];
                        session_regenerate_id(true);
                        $messages[] = 'Giris basarili.';
                        log_security_event('login_success', $username, 'Basarili giris');
                    } else {
                        $attempt = register_failed_login($username);
                        if ($attempt['locked']) {
                            $errors[] = '5 yanlis deneme. 10 dakika kilitlendi.';
                            log_security_event('login_locked', $username, '5 hatali deneme sonrasi kilitlendi');
                        } else {
                            $left = 5 - $attempt['failed_count'];
                            $errors[] = 'Giris basarisiz. Kalan deneme hakki: ' . $left;
                            log_security_event('login_failed', $username, 'Hatali sifre veya kullanici');
                        }
                    }
                }
            }
        }
    }
}

$user = current_user();
$logs = recent_security_logs(20);
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guvenlik Temeli Mini Lab</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #f6f8fa; }
        .wrap { max-width: 980px; margin: 0 auto; background: white; padding: 1.5rem; border-radius: 10px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .card { border: 1px solid #d0d7de; border-radius: 8px; padding: 1rem; }
        label { display: block; margin-top: .5rem; }
        input { width: 100%; padding: .5rem; margin-top: .25rem; box-sizing: border-box; }
        button { margin-top: .75rem; padding: .5rem .75rem; }
        .msg { background: #dafbe1; padding: .75rem; border: 1px solid #aceebb; margin-bottom: .5rem; }
        .err { background: #ffebe9; padding: .75rem; border: 1px solid #ff8182; margin-bottom: .5rem; }
        code { background: #f0f3f6; padding: 2px 6px; border-radius: 4px; }
        .meter { margin-top: .5rem; }
        .meter-track { width: 100%; height: 10px; background: #eaeef2; border-radius: 999px; overflow: hidden; }
        .meter-fill { height: 100%; width: 0%; background: #cf222e; transition: width .2s ease, background-color .2s ease; }
        .meter-text { margin-top: .35rem; font-size: .9rem; color: #57606a; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { border: 1px solid #d0d7de; padding: .45rem; text-align: left; vertical-align: top; }
        th { background: #f6f8fa; }
        .table-wrap { overflow-x: auto; }
        .links { display: flex; gap: 1rem; margin-top: .5rem; }
        @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Guvenlik Temeli: Login Mini Lab</h1>
    <p>Bu lab: guclu sifre, guvenli hash, CSRF, rate limit ve log kaydi ornegi gosterir.</p>

    <?php foreach ($messages as $message): ?>
        <div class="msg"><?= h($message) ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $error): ?>
        <div class="err"><?= h($error) ?></div>
    <?php endforeach; ?>

    <?php if ($user): ?>
        <div class="card">
            <h2>Hos geldin, <?= h($user['username']) ?></h2>
            <p>Kullanici ID: <code><?= h((string) $user['id']) ?></code></p>
            <p>Kayit tarihi: <code><?= h($user['created_at']) ?></code></p>
            <div class="links">
                <a href="admin_logs.php">Admin Logs</a>
                <a href="?logout=1">Cikis yap</a>
            </div>
        </div>
    <?php else: ?>
        <div class="grid">
            <div class="card">
                <h2>Kayit Ol</h2>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="register">
                    <label>Kullanici adi
                        <input type="text" name="username" required minlength="3">
                    </label>
                    <label>Sifre
                        <input id="registerPassword" type="password" name="password" required>
                    </label>
                    <div class="meter">
                        <div class="meter-track"><div id="strengthBar" class="meter-fill"></div></div>
                        <div id="strengthText" class="meter-text">Guvenlik skoru: Bekleniyor</div>
                    </div>
                    <button type="submit">Kayit</button>
                </form>
                <p><strong>Sifre kurali:</strong> 12+ karakter, buyuk/kucuk, rakam, ozel karakter.</p>
            </div>

            <div class="card">
                <h2>Giris Yap</h2>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="login">
                    <label>Kullanici adi
                        <input type="text" name="username" required>
                    </label>
                    <label>Sifre
                        <input type="password" name="password" required>
                    </label>
                    <button type="submit">Giris</button>
                </form>
                <p>5 yanlis denemeden sonra 10 dakika gecici kilit.</p>
            </div>
        </div>
    <?php endif; ?>

    <hr>
    <h3>Son Guvenlik Loglari</h3>
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
                <tr><td colspan="5">Henuz log yok.</td></tr>
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

    <hr>
    <h3>Hizli test adimlari</h3>
    <ol>
        <li>Zayif sifre ile kayit dene: red olmali.</li>
        <li>Guclu sifre ile kayit ol ve bar seviyesini gozlemle.</li>
        <li>5 kez yanlis sifre ile giris dene: kilitlenmeli.</li>
        <li>Log tablosunda event kayitlarini kontrol et.</li>
    </ol>
</div>
<script>
(function () {
    const input = document.getElementById('registerPassword');
    const bar = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');

    if (!input || !bar || !text) {
        return;
    }

    function calcScore(value) {
        let score = 0;
        if (value.length >= 8) score += 15;
        if (value.length >= 12) score += 20;
        if (/[a-z]/.test(value)) score += 15;
        if (/[A-Z]/.test(value)) score += 15;
        if (/[0-9]/.test(value)) score += 15;
        if (/[^A-Za-z0-9]/.test(value)) score += 15;
        if (/(.)\1{2,}/.test(value)) score -= 10;
        if (/password|123456|qwerty|admin|letmein/i.test(value)) score -= 25;
        if (score < 0) score = 0;
        if (score > 100) score = 100;
        return score;
    }

    function labelAndColor(score) {
        if (score < 30) return { label: 'Cok zayif', color: '#cf222e' };
        if (score < 55) return { label: 'Zayif', color: '#d29922' };
        if (score < 75) return { label: 'Orta', color: '#bf8700' };
        if (score < 90) return { label: 'Iyi', color: '#1a7f37' };
        return { label: 'Cok guclu', color: '#116329' };
    }

    function update() {
        const score = calcScore(input.value);
        const meta = labelAndColor(score);
        bar.style.width = score + '%';
        bar.style.backgroundColor = meta.color;
        text.textContent = 'Guvenlik skoru: ' + meta.label + ' (' + score + '/100)';
    }

    input.addEventListener('input', update);
    update();
})();
</script>
</body>
</html>
