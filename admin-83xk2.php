<?php
// admin-83xk2.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

session_start();

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}

function is_admin(): bool {
  return !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function require_admin_or_404() {
  if (!is_admin()) {
    http_response_code(404);
    echo "Not Found";
    exit;
  }
}

$pdo = db();

// Logout
if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: admin-83xk2.php');
  exit;
}

// Login submit
$login_error = '';
if (!is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
  $u = (string)($_POST['username'] ?? '');
  $p = (string)($_POST['password'] ?? '');

  if ($u === ADMIN_USER && password_verify($p, ADMIN_PASS_HASH)) {
    $_SESSION['is_admin'] = true;
    csrf_token();
    header('Location: admin-83xk2.php');
    exit;
  } else {
    $login_error = 'Грешно потребителско име или парола.';
  }
}

// Admin actions: approve/delete
if (is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['approve','delete'], true)) {
  if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(400);
    echo "Bad Request";
    exit;
  }

  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    if ($_POST['action'] === 'approve') {
      $stmt = $pdo->prepare("UPDATE reviews SET status='approved', approved_at=:t WHERE id=:id");
      $stmt->execute([':t' => gmdate('c'), ':id' => $id]);
    } else if ($_POST['action'] === 'delete') {
      $stmt = $pdo->prepare("DELETE FROM reviews WHERE id=:id");
      $stmt->execute([':id' => $id]);
    }
  }

  header('Location: admin-83xk2.php');
  exit;
}

// If not logged -> show login (no mention in menus, direct URL only)
if (!is_admin()) {
  ?>
  <!doctype html>
  <html lang="bg">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin</title>
    <meta name="robots" content="noindex,nofollow">
    <style>
      body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:#f6f8fb; margin:0;}
      .wrap{max-width:420px;margin:8vh auto;background:#fff;border:1px solid #e6eaf2;border-radius:14px;padding:22px;box-shadow:0 12px 30px rgba(0,0,0,.06)}
      h1{font-size:18px;margin:0 0 14px}
      label{display:block;font-size:12px;color:#334;margin-top:10px}
      input{width:100%;padding:10px 12px;border:1px solid #d9e1ef;border-radius:10px;margin-top:6px}
      button{width:100%;margin-top:14px;padding:10px 12px;border:0;border-radius:10px;background:#2f6fed;color:#fff;font-weight:700;cursor:pointer}
      .err{margin-top:10px;color:#b00020;font-size:13px}
      .note{margin-top:10px;color:#667;font-size:12px}
    </style>
  </head>
  <body>
    <div class="wrap">
      <h1>Вход за администратор</h1>
      <form method="post" autocomplete="off">
        <input type="hidden" name="action" value="login">
        <label>Потребител *</label>
        <input name="username" required>
        <label>Парола *</label>
        <input name="password" type="password" required>
        <button type="submit">Вход</button>
        <?php if ($login_error): ?><div class="err"><?= htmlspecialchars($login_error) ?></div><?php endif; ?>
        <div class="note">Тази страница не е публично линкната.</div>
      </form>
    </div>
  </body>
  </html>
  <?php
  exit;
}

// Admin view
require_admin_or_404();

$pending = $pdo->query("SELECT * FROM reviews WHERE status='pending' ORDER BY datetime(created_at) DESC")->fetchAll();
$approved = $pdo->query("SELECT * FROM reviews WHERE status='approved' ORDER BY datetime(approved_at) DESC, datetime(created_at) DESC LIMIT 50")->fetchAll();

?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reviews Admin</title>
  <meta name="robots" content="noindex,nofollow">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#f6f8fb;margin:0;color:#1b2b3a}
    header{display:flex;justify-content:space-between;align-items:center;padding:16px 18px;background:#fff;border-bottom:1px solid #e6eaf2;position:sticky;top:0}
    .wrap{max-width:1100px;margin:18px auto;padding:0 14px}
    h2{margin:18px 0 10px;font-size:18px}
    .grid{display:grid;grid-template-columns:1fr;gap:10px}
    .card{background:#fff;border:1px solid #e6eaf2;border-radius:14px;padding:14px;box-shadow:0 10px 24px rgba(0,0,0,.05)}
    .meta{display:flex;gap:10px;flex-wrap:wrap;font-size:12px;color:#667}
    .msg{margin-top:10px;white-space:pre-wrap}
    .actions{margin-top:12px;display:flex;gap:10px}
    button{padding:9px 12px;border-radius:10px;border:0;cursor:pointer;font-weight:700}
    .ok{background:#20a55f;color:#fff}
    .del{background:#e24343;color:#fff}
    a{color:#2f6fed;text-decoration:none;font-weight:700}
    .badge{display:inline-flex;align-items:center;gap:6px;background:#eef4ff;color:#2f6fed;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}
    .stars{color:#f5b301;font-weight:900}
  </style>
</head>
<body>
<header>
  <div class="badge">Admin: Отзиви</div>
  <div><a href="?logout=1">Изход</a></div>
</header>

<div class="wrap">
  <h2>Чакащи одобрение (<?= count($pending) ?>)</h2>
  <div class="grid">
    <?php if (!count($pending)): ?>
      <div class="card">Няма чакащи отзиви.</div>
    <?php endif; ?>

    <?php foreach ($pending as $r): ?>
      <div class="card">
        <div class="meta">
          <div><strong><?= htmlspecialchars($r['name']) ?></strong><?= $r['company'] ? ' — ' . htmlspecialchars($r['company']) : '' ?></div>
          <div class="stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5-(int)$r['rating']) ?></div>
          <div><?= htmlspecialchars($r['email'] ?? '') ?></div>
          <div><?= htmlspecialchars($r['created_at']) ?></div>
        </div>
        <div class="msg"><?= htmlspecialchars($r['message']) ?></div>

        <div class="actions">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="ok" type="submit">Одобри</button>
          </form>

          <form method="post" onsubmit="return confirm('Да изтрия ли този отзив?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="del" type="submit">Изтрий</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <h2>Последно одобрени</h2>
  <div class="grid">
    <?php if (!count($approved)): ?>
      <div class="card">Няма одобрени отзиви още.</div>
    <?php endif; ?>
    <?php foreach ($approved as $r): ?>
      <div class="card">
        <div class="meta">
          <div><strong><?= htmlspecialchars($r['name']) ?></strong><?= $r['company'] ? ' — ' . htmlspecialchars($r['company']) : '' ?></div>
          <div class="stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5-(int)$r['rating']) ?></div>
          <div>Одобрен: <?= htmlspecialchars($r['approved_at'] ?? '-') ?></div>
        </div>
        <div class="msg"><?= htmlspecialchars($r['message']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
