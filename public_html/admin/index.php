<?php
require __DIR__ . '/../includes/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');

if (!db_ready()) {
    exit('The site is not installed yet. Open /install.php first.');
}
if (is_admin()) redirect('dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $window = date('Y-m-d H:i:s', time() - 900);
    $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?');
    $st->execute([client_ip(), $window]);
    $fails = (int)$st->fetchColumn();

    if ($fails >= 5) {
        $error = 'Too many failed sign-ins. Wait 15 minutes, then try again.';
    } elseif (!csrf_ok()) {
        $error = 'The form expired. Reload the page and try again.';
    } else {
        $st = $pdo->prepare('SELECT id, password_hash FROM admins WHERE username = ?');
        $st->execute([trim((string)($_POST['username'] ?? ''))]);
        $row = $st->fetch();
        $pass = (string)($_POST['password'] ?? '');
        // Always run a hash check so timing doesn't reveal valid usernames
        $hash = $row['password_hash'] ?? password_hash(random_bytes(12), PASSWORD_DEFAULT);
        if (password_verify($pass, $hash) && $row) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$row['id'];
            $_SESSION['admin_seen'] = time();
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($pass, PASSWORD_DEFAULT), $row['id']]);
            }
            $pdo->prepare('UPDATE admins SET last_login = ? WHERE id = ?')->execute([now(), $row['id']]);
            $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([client_ip()]);
            redirect('dashboard.php');
        }
        $pdo->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)')->execute([client_ip(), now()]);
        $error = 'That username and password do not match.';
    }
}
$f = flash();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Sign in — Power Plumbing admin</title>
<link rel="icon" href="../assets/img/logo-mark.svg" type="image/svg+xml">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="auth">
<main class="auth-card">
  <img src="../assets/img/logo.svg" alt="Power Plumbing Services Ltd" class="auth-logo">
  <h1>Admin sign in</h1>
  <?php if ($f): ?><p class="note <?= e($f['type']) ?>"><?= e($f['msg']) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="note err"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Username<input name="username" required autocomplete="username" autofocus></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn" type="submit">Sign in</button>
  </form>
  <p class="muted"><a href="../">Back to the website</a></p>
</main>
</body>
</html>
