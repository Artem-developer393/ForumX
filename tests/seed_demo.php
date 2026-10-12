<?php
/** Local-only demo fixture. Run via tests/setup_demo.py, never from a web route. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('DEMO_SEED_ALLOW') !== '1') {
    fwrite(STDERR, "Demo seed requires CLI and explicit DEMO_SEED_ALLOW=1.\n");
    exit(1);
}
$password = getenv('DEMO_SEED_PASSWORD');
if (!is_string($password) || strlen($password) < 20) {
    fwrite(STDERR, "Missing strong DEMO_SEED_PASSWORD.\n");
    exit(1);
}
$host = getenv('DB_HOST') ?: 'db';
$db = getenv('DB_NAME') ?: 'webforum';
$user = getenv('DB_USER') ?: 'forumx';
$pass = getenv('DB_PASS');
if ($pass === false) {
    fwrite(STDERR, "Missing DB_PASS.\n");
    exit(1);
}
$pdo = new PDO('mysql:host=' . $host . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . $db . ';charset=utf8mb4', $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$accounts = [
    ['demo_alice', 'demo_alice@example.test', 1],
    ['demo_bob', 'demo_bob@example.test', 1],
    ['demo_admin', 'demo_admin@example.test', 2],
];
$hash = password_hash($password, PASSWORD_BCRYPT);
$pdo->beginTransaction();
try {
    $check = $pdo->prepare('SELECT id, login, email FROM users WHERE login = ? OR email = ? FOR UPDATE');
    $insert = $pdo->prepare('INSERT INTO users (login, email, password, role, is_banned, email_verified_at) VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP)');
    $update = $pdo->prepare('UPDATE users SET password = ?, role = ?, is_banned = 0, email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP), email_verification_code_hash = NULL, email_verification_expires_at = NULL WHERE id = ?');
    foreach ($accounts as [$login, $email, $role]) {
        $check->execute([$login, $email]);
        $matches = $check->fetchAll(PDO::FETCH_ASSOC);
        if (count($matches) > 1 || (count($matches) === 1 && ($matches[0]['login'] !== $login || $matches[0]['email'] !== $email))) {
            throw new RuntimeException('Reserved demo identity conflicts with existing account: ' . $login);
        }
        if (!$matches) {
            $insert->execute([$login, $email, $hash, $role]);
        } else {
            $update->execute([$hash, $role, (int)$matches[0]['id']]);
        }
    }
    $pdo->commit();
    echo "Demo users prepared: demo_alice, demo_bob, demo_admin.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Demo seed failed: ' . $e->getMessage() . "\n");
    exit(1);
}
