<?php

$db = new PDO('sqlite:' . __DIR__ . '/../database/database.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$count = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
echo "users_count\t{$count}\n";

$sql = 'SELECT id, role, name, email, email_verified_at FROM users ORDER BY role, id';
foreach ($db->query($sql) as $row) {
    echo implode("\t", [
        $row['id'] ?? '',
        $row['role'] ?? '',
        $row['name'] ?? '',
        $row['email'] ?? '',
        $row['email_verified_at'] ?? '',
    ]), "\n";
}
