<?php
require_once __DIR__ . '/../config.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    header('Location: ' . BASE . '/admin/dashboard');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $pdo = getDb();
    $stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE username = ?");
    $stmt->execute([$user]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && password_verify($pass, $row['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $user;
        $pending = trim((string) ($_SESSION['admin_scan_pending_code'] ?? ''));
        unset($_SESSION['admin_scan_pending_code']);
        if ($pending !== '' && guest_qr_secret_looks_valid($pending)) {
            header('Location: ' . BASE . '/admin/scan?code=' . rawurlencode($pending));
            exit;
        }
        header('Location: ' . BASE . '/admin/dashboard');
        exit;
    }
    if ($user !== '' || $pass !== '') {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
</head>
<body>
    <div class="admin-wrap">
        <form class="login-form" method="post" action="<?= BASE ?>/admin">
            <h1>Admin login</h1>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-submit">Log in</button>
        </form>
    </div>
</body>
</html>
