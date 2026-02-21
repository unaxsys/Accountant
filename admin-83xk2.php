<?php
// admin-83xk2.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

session_start();

/** ---------------- Helpers ---------------- */

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}

function is_admin(): bool {
  return !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function require_admin_or_404(): void {
  if (!is_admin()) {
    http_response_code(404);
    echo "Not Found";
    exit;
  }
}

function require_csrf_or_400(): void {
  if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(400);
    echo "Bad Request";
    exit;
  }
}

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Safe substring: uses mb_substr if available, otherwise falls back to substr.
 * This prevents HTTP 500 when php-mbstring is not installed.
 */
function cut($s, int $max): string {
  $s = (string)$s;
  if ($max <= 0) return '';
  if (function_exists('mb_substr')) {
    return mb_substr($s, 0, $max, 'UTF-8');
  }
  return substr($s, 0, $max);
}

function redirect_with_msg(string $anchor = '', string $msg = ''): void {
  $to = 'admin-83xk2.php';
  $qs = [];
  if ($msg !== '') $qs['msg'] = $msg;
  if ($qs) $to .= '?' . http_build_query($qs);
  if ($anchor !== '') $to .= '#' . rawurlencode($anchor);
  header('Location: ' . $to);
  exit;
}

/** ---------------- Bootstrap ---------------- */

$pdo = db();

// Logout
if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: admin-83xk2.php');
  exit;
}

// Login submit
$login_error = '';
if (!is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'login')) {
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

// If not logged -> show login (direct URL only)
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
      button{width:100%;margin-top:14px;padding:10px 12px;border:0;border-radius:10px;background:#2f6fed;color:#fff;font-weight:800;cursor:pointer}
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
        <?php if ($login_error): ?><div class="err"><?= h($login_error) ?></div><?php endif; ?>
        <div class="note">Тази страница не е публично линкната.</div>
      </form>
    </div>
  </body>
  </html>
  <?php
  exit;
}

// Admin actions
require_admin_or_404();
csrf_token();

/** ---------------- Actions ---------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  if (in_array($action, ['approve', 'delete', 'update'], true)) {
    require_csrf_or_400();

    $id = (int)($_POST['id'] ?? 0);
    $anchor = (string)($_POST['anchor'] ?? '');
    if ($anchor === '') $anchor = 'pending';

    if ($id > 0) {
      if ($action === 'approve') {
        // ✅ FIX: store SQLite-friendly timestamp so sorting/filtering is always stable
        $stmt = $pdo->prepare("UPDATE reviews SET status='approved', approved_at = datetime('now') WHERE id=:id");
        $stmt->execute([':id' => $id]);
        redirect_with_msg('approved', 'Отзивът е одобрен.');
      }

      if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id=:id");
        $stmt->execute([':id' => $id]);
        redirect_with_msg($anchor, 'Отзивът е изтрит.');
      }

      if ($action === 'update') {
        $name = trim((string)($_POST['name'] ?? ''));
        $company = trim((string)($_POST['company'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $rating = (int)($_POST['rating'] ?? 5);
        $message = trim((string)($_POST['message'] ?? ''));

        if ($name === '' || $email === '' || $message === '') {
          redirect_with_msg('approved', 'Моля попълни: Име, Имейл и Отзив.');
        }

        if ($rating < 1) $rating = 1;
        if ($rating > 5) $rating = 5;

        // length limits (safe even without mbstring)
        $name = cut($name, 120);
        $company = cut($company, 120);
        $email = cut($email, 190);
        $message = cut($message, 2000);

        $stmt = $pdo->prepare("
          UPDATE reviews
          SET name=:name, company=:company, email=:email, rating=:rating, message=:message
          WHERE id=:id
        ");
        $stmt->execute([
          ':name' => $name,
          ':company' => $company,
          ':email' => $email,
          ':rating' => $rating,
          ':message' => $message,
          ':id' => $id,
        ]);

        redirect_with_msg('approved', 'Промените са запазени.');
      }
    }

    redirect_with_msg($anchor, 'Невалиден отзив.');
  }
}

/** ---------------- Data for view ---------------- */

$pending = $pdo->query("SELECT * FROM reviews WHERE status='pending' ORDER BY datetime(created_at) DESC")->fetchAll();

/**
 * ✅ FIX: Robust ordering for approved_at, even if older rows used ISO 8601 (T + timezone)
 * We normalize approved_at to SQLite datetime format for ordering.
 */
$approved = $pdo->query("
  SELECT *
  FROM reviews
  WHERE status='approved'
  ORDER BY
    COALESCE(
      datetime(approved_at),
      datetime(replace(replace(approved_at,'T',' '),'+00:00',''))
    ) DESC,
    datetime(created_at) DESC
  LIMIT 200
")->fetchAll();

$editId = (int)($_GET['edit'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');
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
    header{display:flex;justify-content:space-between;align-items:center;padding:16px 18px;background:#fff;border-bottom:1px solid #e6eaf2;position:sticky;top:0;z-index:10}
    .wrap{max-width:1100px;margin:18px auto;padding:0 14px}
    h2{margin:18px 0 10px;font-size:18px}
    .grid{display:grid;grid-template-columns:1fr;gap:10px}
    .card{background:#fff;border:1px solid #e6eaf2;border-radius:14px;padding:14px;box-shadow:0 10px 24px rgba(0,0,0,.05)}
    .meta{display:flex;gap:10px;flex-wrap:wrap;font-size:12px;color:#667;align-items:center}
    .msg{margin-top:10px;white-space:pre-wrap}
    .actions{margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    button{padding:9px 12px;border-radius:10px;border:0;cursor:pointer;font-weight:800}
    .ok{background:#20a55f;color:#fff}
    .del{background:#e24343;color:#fff}
    .edbtn{display:inline-flex;align-items:center;justify-content:center;padding:9px 12px;border-radius:10px;background:#2f6fed;color:#fff;font-weight:800;text-decoration:none}
    a{color:#2f6fed;text-decoration:none;font-weight:800}
    .badge{display:inline-flex;align-items:center;gap:8px;background:#eef4ff;color:#2f6fed;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:800}
    .stars{color:#f5b301;font-weight:900}
    .flash{max-width:1100px;margin:14px auto 0;padding:0 14px}
    .flash > div{background:#fff;border:1px solid #e6eaf2;border-radius:14px;padding:12px 14px;box-shadow:0 10px 24px rgba(0,0,0,.05)}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
    label{display:block;font-size:12px;color:#334;margin-top:10px}
    input, textarea, select{width:100%;padding:10px 12px;border:1px solid #d9e1ef;border-radius:10px;margin-top:6px;font:inherit}
    textarea{min-height:110px;resize:vertical}
    .muted{color:#667;font-size:12px}
    .toplinks{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
    .cancel{display:inline-flex;align-items:center;justify-content:center;width:100%;padding:10px 12px;border-radius:10px;border:1px solid #d9e1ef;background:#fff;color:#2f6fed;font-weight:800;text-decoration:none}
  </style>
</head>
<body>
<header>
  <div class="badge">Admin: Отзиви</div>
  <div class="toplinks">
    <a href="#pending">Чакащи (<?= count($pending) ?>)</a>
    <a href="#approved">Одобрени (<?= count($approved) ?>)</a>
    <a href="?logout=1">Изход</a>
  </div>
</header>

<?php if ($msg !== ''): ?>
  <div class="flash"><div><?= h($msg) ?></div></div>
<?php endif; ?>

<div class="wrap">
  <h2 id="pending">Чакащи одобрение (<?= count($pending) ?>)</h2>
  <div class="grid">
    <?php if (!count($pending)): ?>
      <div class="card">Няма чакащи отзиви.</div>
    <?php endif; ?>

    <?php foreach ($pending as $r): ?>
      <div class="card" id="p<?= (int)$r['id'] ?>">
        <div class="meta">
          <div><strong><?= h($r['name']) ?></strong><?= $r['company'] ? ' — ' . h($r['company']) : '' ?></div>
          <div class="stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5-(int)$r['rating']) ?></div>
          <div><?= h($r['email'] ?? '') ?></div>
          <div><?= h($r['created_at']) ?></div>
        </div>
        <div class="msg"><?= h($r['message']) ?></div>

        <div class="actions">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="anchor" value="pending">
            <button class="ok" type="submit">Одобри</button>
          </form>

          <form method="post" onsubmit="return confirm('Да изтрия ли този отзив?');">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="anchor" value="pending">
            <button class="del" type="submit">Изтрий</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <h2 id="approved">Одобрени (последни <?= count($approved) ?>)</h2>
  <div class="grid">
    <?php if (!count($approved)): ?>
      <div class="card">Няма одобрени отзиви още.</div>
    <?php endif; ?>

    <?php foreach ($approved as $r): ?>
      <?php $id = (int)$r['id']; $isEditing = ($editId > 0 && $editId === $id); ?>
      <div class="card" id="a<?= $id ?>">
        <div class="meta">
          <div><strong><?= h($r['name']) ?></strong><?= $r['company'] ? ' — ' . h($r['company']) : '' ?></div>
          <div class="stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5-(int)$r['rating']) ?></div>
          <div>Одобрен: <?= h($r['approved_at'] ?? '-') ?></div>
          <div class="muted">ID: <?= $id ?></div>
        </div>

        <?php if (!$isEditing): ?>
          <div class="msg"><?= h($r['message']) ?></div>

          <div class="actions">
            <a class="edbtn" href="?edit=<?= $id ?>#a<?= $id ?>">Редактирай</a>

            <form method="post" onsubmit="return confirm('Да изтрия ли този отзив?');">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $id ?>">
              <input type="hidden" name="anchor" value="approved">
              <button class="del" type="submit">Изтрий</button>
            </form>
          </div>
        <?php else: ?>
          <form method="post" style="margin-top:12px">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="anchor" value="approved">

            <div class="row">
              <div>
                <label>Име и фамилия *</label>
                <input name="name" required value="<?= h($r['name']) ?>">
              </div>
              <div>
                <label>Фирма (по желание)</label>
                <input name="company" value="<?= h($r['company'] ?? '') ?>">
              </div>
            </div>

            <div class="row3">
              <div>
                <label>Имейл *</label>
                <input name="email" type="email" required value="<?= h($r['email'] ?? '') ?>">
              </div>
              <div>
                <label>Оценка *</label>
                <select name="rating" required>
                  <?php for ($i=1; $i<=5; $i++): ?>
                    <option value="<?= $i ?>" <?= ((int)$r['rating'] === $i) ? 'selected' : '' ?>><?= $i ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div>
                <label>&nbsp;</label>
                <a class="cancel" href="admin-83xk2.php#approved">Отказ</a>
              </div>
            </div>

            <label>Отзив *</label>
            <textarea name="message" required><?= h($r['message']) ?></textarea>

            <div class="actions">
              <button class="ok" type="submit">Запази</button>
            </div>

            <div class="muted">След “Запази” ще те върне към секцията “Одобрени”.</div>
          </form>

          <div class="actions" style="margin-top:10px">
            <form method="post" onsubmit="return confirm('Да изтрия ли този отзив?');">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $id ?>">
              <input type="hidden" name="anchor" value="approved">
              <button class="del" type="submit">Изтрий този отзив</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>