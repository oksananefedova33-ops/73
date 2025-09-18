<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';
$authorized = $_SESSION['is_admin'] ?? false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    if ($pass === ADMIN_PASSWORD) {
        $_SESSION['is_admin'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Неверный пароль';
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Админ-панель — Zerro Blog</title>
  <style>
    :root { --bg:#0b0f14; --panel:#121821; --card:#17202b; --muted:#8aa0b3; --accent:#5fb3ff; }
    * { box-sizing:border-box; }
    html, body { height:100%; }
    body { margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto; background: var(--bg); color:#dbe7f3; }
    .card { background: var(--panel); border:1px solid #1f2b3b; border-radius:14px; padding:16px; }
    h1 { margin:0 0 12px 0; font-size:22px; }
    .btn { border:1px solid #223244; background:#1a2533; color:#e6f0fa; padding:8px 12px; border-radius:10px; cursor:pointer; }
    .btn:hover { background:#203043; }
    .muted { color:#9fb2c6; font-size:12px; }
    .input { background:#111925; border:1px solid #213247; color:#e4eef9; padding:8px 10px; border-radius:10px; width:100%; }
    .login { max-width:360px; margin:12vh auto; }
    /* Fullscreen editor iframe */
    .fs-wrap { position:fixed; inset:0; }
    .fs-wrap iframe { position:absolute; inset:0; width:100vw; height:100vh; border:0; display:block; background:#0a0f15; }
    /* Floating controls */
    .floating { display:none !important; }
  </style>
<link rel="stylesheet" href="/editor/assets/resize-nav.css?v=20250903-232413">
    <link rel="stylesheet" href="/ui/modules/button-link/button-link.css?v=<?php echo date('YmdHis'); ?>" />
</head>
<body>
<?php if (!$authorized): ?>
  <div class="login card">
    <h1>Вход в админ‑панель</h1>
    <form method="post">
      <label class="muted">Пароль</label>
      <input class="input" type="password" name="password" placeholder="Введите пароль" autofocus />
      <?php if ($error): ?><div class="muted" style="color:#ff8080; margin-top:8px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <div style="margin-top:12px; display:flex; gap:8px; align-items:center;">
        <button class="btn" type="submit">Войти</button>
        <a href="../index.php" class="muted">← На сайт</a>
      </div>
    </form>
  </div>
<?php else: ?>
  <div class="fs-wrap">
    <iframe src="../editor/editor.php" title="Zerro Editor"></iframe>
  </div>
  <div class="floating">
    <a class="btn" href="../index.php" target="_blank">Открыть сайт</a>
    <a class="btn" href="logout.php">Выйти</a>
  </div>
<?php endif; ?>
<script src="/editor/assets/resize-nav.js?v=20250903-232413"></script>
    <script defer src="/ui/modules/button-link/button-link.js?v=<?php echo date('YmdHis'); ?>"></script>
    <!-- Модули экспорта и удаленного управления -->
<link rel="stylesheet" href="/ui/site-export/site-export.css?v=<?php echo date('YmdHis'); ?>">
<script src="/ui/site-export/site-export.js?v=<?php echo date('YmdHis'); ?>"></script>
<link rel="stylesheet" href="/ui/remote-control/remote-control.css?v=<?php echo date('YmdHis'); ?>">
<script src="/ui/remote-control/remote-control.js?v=<?php echo date('YmdHis'); ?>"></script>
</body>
</html>
