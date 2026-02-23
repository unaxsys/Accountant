<?php
// admin-83xk2.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

session_start();

$tab = (string)($_GET['tab'] ?? 'reviews');
if (!in_array($tab, ['reviews', 'articles'], true)) {
  $tab = 'reviews';
}

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

function slugify_post(string $text): string {
  $text = trim(mb_strtolower($text, 'UTF-8'));
  $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sht','ъ'=>'a','ь'=>'','ю'=>'yu','я'=>'ya'];
  $text = strtr($text, $map);
  $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
  $text = trim((string)$text, '-');
  return $text !== '' ? $text : 'statia';
}

function unique_post_slug(PDO $pdo, string $base, ?int $ignoreId = null): string {
  $slug = $base;
  $i = 1;
  while (true) {
    $sql = 'SELECT id FROM posts WHERE slug = :slug';
    $params = [':slug' => $slug];
    if ($ignoreId !== null) {
      $sql .= ' AND id != :id';
      $params[':id'] = $ignoreId;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    if (!$stmt->fetch()) return $slug;
    $slug = $base . '-' . $i;
    $i++;
  }
}

function sanitize_trusted_html(string $html): string {
  $html = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $html) ?? $html;
  return $html;
}

function utf8_cut(string $text, int $max): string {
  if ($max <= 0) return '';
  if (function_exists('mb_substr')) return mb_substr($text, 0, $max, 'UTF-8');
  return substr($text, 0, $max);
}

$ARTICLES_FILE = __DIR__ . '/articles.json';

function load_articles_json(string $file): array {
  if (!file_exists($file)) return [];
  $raw = file_get_contents($file);
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function save_articles_json(string $file, array $items): bool {
  $json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  return file_put_contents($file, $json, LOCK_EX) !== false;
}

function slugify_bg(string $text): string {
  $text = trim(mb_strtolower($text, 'UTF-8'));
  $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
          'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
          'ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sht','ъ'=>'a','ь'=>'','ю'=>'yu','я'=>'ya'];
  $text = strtr($text, $map);
  $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
  $text = trim((string)$text, '-');
  return $text ?: 'statia';
}

function unique_slug_json(array $items, string $base, ?string $ignoreId = null): string {
  $slug = $base;
  $i = 1;
  $exists = function ($s) use ($items, $ignoreId) {
    foreach ($items as $a) {
      if (($a['slug'] ?? '') === $s && (!$ignoreId || ($a['id'] ?? '') !== $ignoreId)) return true;
    }
    return false;
  };
  while ($exists($slug)) {
    $slug = $base . '-' . $i;
    $i++;
  }
  return $slug;
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
      <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-NBCQS8P4KP"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-NBCQS8P4KP');
    </script>
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

$article_flash = '';
$article_edit = null;

if (is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'article_save')) {
  if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(400);
    exit('Bad CSRF');
  }

  try {
    $postId = (int)($_POST['id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $slugIn = trim((string)($_POST['slug'] ?? ''));
    $meta = trim((string)($_POST['meta_description'] ?? ''));
    $excerpt = trim((string)($_POST['excerpt'] ?? ''));
    $content = sanitize_trusted_html(trim((string)($_POST['content_html'] ?? '')));
    $tags = trim((string)($_POST['tags'] ?? ''));
    $status = !empty($_POST['publish_now']) ? 'published' : 'draft';
    $seoTitle = trim((string)($_POST['seo_title'] ?? ''));
    $focusKeyword = trim((string)($_POST['focus_keyword'] ?? ''));
    $coverAlt = trim((string)($_POST['cover_alt'] ?? ''));

    if ($title === '' || $content === '') {
      throw new RuntimeException('Заглавие и съдържание са задължителни.');
    }


    $pdoPosts = posts_pdo();
    $slug = unique_post_slug($pdoPosts, slugify_post($slugIn !== '' ? $slugIn : $title), $postId > 0 ? $postId : null);

    $coverImage = null;
    if (!empty($_FILES['cover_image']['name']) && is_uploaded_file($_FILES['cover_image']['tmp_name'])) {
      $ext = strtolower(pathinfo((string)$_FILES['cover_image']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
        throw new RuntimeException('Невалиден формат за cover image (jpg, jpeg, png, webp).');
      }
      $maxSize = 5 * 1024 * 1024;
      if ((int)($_FILES['cover_image']['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('Файлът е твърде голям (макс. 5MB).');
      }
      $uploadDir = __DIR__ . '/uploads/blog';
      if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Не може да се създаде upload папката.');
      }
      $safeBase = slugify_post($slug !== '' ? $slug : $title);
      $fname = $safeBase . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
      $target = $uploadDir . '/' . $fname;
      if (!move_uploaded_file($_FILES['cover_image']['tmp_name'], $target)) {
        throw new RuntimeException('Неуспешно качване на cover image.');
      }
      $coverImage = '/uploads/blog/' . $fname;
    }

    $now = date('Y-m-d H:i:s');
    if ($postId > 0) {
      $rowStmt = $pdoPosts->prepare('SELECT * FROM posts WHERE id=:id LIMIT 1');
      $rowStmt->execute([':id' => $postId]);
      $existing = $rowStmt->fetch();
      if (!$existing) {
        throw new RuntimeException('Не намерих статията за редакция.');
      }
      if ($coverImage === null) $coverImage = $existing['cover_image'];
      $publishedAt = $existing['published_at'];
      if ($status === 'published' && empty($publishedAt)) $publishedAt = $now;
      if ($status === 'draft') $publishedAt = null;

      $u = $pdoPosts->prepare('UPDATE posts SET slug=:slug,title=:title,meta_description=:meta,excerpt=:excerpt,content_html=:content,cover_image=:cover,tags=:tags,status=:status,published_at=:published_at,updated_at=:updated_at WHERE id=:id');
      $u->execute([
        ':slug'=>$slug, ':title'=>$title, ':meta'=>utf8_cut($meta,160), ':excerpt'=>$excerpt,
        ':content'=>$content, ':cover'=>$coverImage, ':tags'=>$tags !== '' ? $tags : null,
        ':status'=>$status, ':published_at'=>$publishedAt, ':updated_at'=>$now, ':id'=>$postId,
      ]);
    } else {
      $publishedAt = $status === 'published' ? $now : null;
      $i = $pdoPosts->prepare('INSERT INTO posts (title,seo_title,slug,meta_description,focus_keyword,excerpt,content_html,cover_image,cover_alt,tags,status,published_at,created_at) VALUES (:title,:seo_title,:slug,:meta,:focus_keyword,:excerpt,:content,:cover,:cover_alt,:tags,:status,:published_at,NOW())');
      $i->execute([
        ':title' => $title,
        ':seo_title' => $seoTitle !== '' ? $seoTitle : $title,
        ':slug' => $slug,
        ':meta' => utf8_cut($meta, 160),
        ':focus_keyword' => $focusKeyword !== '' ? $focusKeyword : null,
        ':excerpt' => $excerpt,
        ':content' => $content,
        ':cover' => $coverImage,
        ':cover_alt' => $coverAlt !== '' ? $coverAlt : null,
        ':tags' => $tags !== '' ? $tags : null,
        ':status' => $status,
        ':published_at' => $publishedAt,
      ]);
    }

    header('Location: admin-83xk2.php?tab=articles');
    exit;
  } catch (Throwable $e) {
    $article_flash = $e->getMessage();
  }
}


if (is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['article_publish', 'article_unpublish'], true)) {
  if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(400);
    exit('Bad CSRF');
  }
  try {
    $postId = (int)($_POST['id'] ?? 0);
    if ($postId > 0) {
      $pdoPosts = posts_pdo();
      if (($_POST['action'] ?? '') === 'article_publish') {
        $st = $pdoPosts->prepare("UPDATE posts SET status='published', published_at=COALESCE(published_at,:now), updated_at=:now WHERE id=:id");
      } else {
        $st = $pdoPosts->prepare("UPDATE posts SET status='draft', updated_at=:now WHERE id=:id");
      }
      $now = date('Y-m-d H:i:s');
      $st->execute([':id'=>$postId, ':now'=>$now]);
    }
    header('Location: admin-83xk2.php?tab=articles');
    exit;
  } catch (Throwable $e) {
    $article_flash = $e->getMessage();
  }
}


if (is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'article_delete')) {
  if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(400);
    exit('Bad CSRF');
  }
  try {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      $pdoPosts = posts_pdo();
      $d = $pdoPosts->prepare('DELETE FROM posts WHERE id=:id');
      $d->execute([':id' => $id]);
    }
    header('Location: admin-83xk2.php?tab=articles');
    exit;
  } catch (Throwable $e) {
    $article_flash = $e->getMessage();
  }
}

if (is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'article_seed')) {
  if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(400);
    exit('Bad CSRF');
  }
  try {
    $pdoPosts = posts_pdo();
    $baseSlug = unique_post_slug($pdoPosts, 'testova-statia');
    $now = date('Y-m-d H:i:s');
    $stmt = $pdoPosts->prepare('INSERT INTO posts (slug,title,meta_description,excerpt,content_html,cover_image,tags,status,published_at,created_at,updated_at) VALUES (:slug,:title,:meta,:excerpt,:content,:cover,:tags,:status,:published_at,:created_at,:updated_at)');
    $stmt->execute([
      ':slug' => $baseSlug,
      ':title' => 'Тестова статия',
      ':meta' => 'Това е тестова SEO meta description за проверка на блога.',
      ':excerpt' => 'Кратко описание на тестовата статия за проверка на листинга.',
      ':content' => '<p>Това е тестово съдържание.</p>',
      ':cover' => null,
      ':tags' => 'тест,пример',
      ':status' => 'published',
      ':published_at' => $now,
      ':created_at' => $now,
      ':updated_at' => $now,
    ]);
    header('Location: admin-83xk2.php?tab=articles');
    exit;
  } catch (Throwable $e) {
    $article_flash = $e->getMessage();
  }
}
if (is_admin() && isset($_GET['article_edit'])) {
  try {
    $id = (int)$_GET['article_edit'];
    if ($id > 0) {
      $pdoPosts = posts_pdo();
      $s = $pdoPosts->prepare('SELECT * FROM posts WHERE id=:id LIMIT 1');
      $s->execute([':id' => $id]);
      $article_edit = $s->fetch() ?: null;
    }
  } catch (Throwable $e) {
    $article_flash = $e->getMessage();
  }
}

try {
  $articles = is_admin() ? posts_pdo()->query('SELECT * FROM posts ORDER BY created_at DESC LIMIT 20')->fetchAll() : [];
} catch (Throwable $e) {
  $articles = [];
  if ($article_flash === '') {
    $article_flash = $e->getMessage();
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
    .btn{display:inline-flex;align-items:center;justify-content:center;padding:9px 12px;border-radius:10px;background:#2f6fed;color:#fff;font-weight:800;text-decoration:none;border:0}
    .btn-ghost{background:#fff;color:#2f6fed;border:1px solid #d9e1ef}
    .danger{background:#e24343;color:#fff}
    .notice{background:#fff4e5;border:1px solid #ffce8a;color:#8a4b00;padding:10px 12px;border-radius:10px}
  </style>
    <!-- Google tag  (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-NBCQS8P4KP"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-NBCQS8P4KP');
    </script>
</head>
<body>
<header>
  <div class="badge">Admin: Отзиви</div>
  <div class="toplinks">
    <a href="admin-83xk2.php?tab=reviews#pending">Чакащи (<?= count($pending) ?>)</a>
    <a href="admin-83xk2.php?tab=reviews#approved">Одобрени (<?= count($approved) ?>)</a>
    <a href="admin-83xk2.php?tab=articles">Статии</a>
    <a href="?logout=1">Изход</a>
  </div>
</header>

<?php if ($msg !== ''): ?>
  <div class="flash"><div><?= h($msg) ?></div></div>
<?php endif; ?>

<div class="wrap">
  <?php if ($tab === 'reviews'): ?>
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
  <?php endif; ?>

  <?php if ($tab === 'articles'): ?>
    <h2>Статии</h2>

    <?php if (!empty($article_flash)): ?>
      <div class="notice"><?= h($article_flash) ?></div>
    <?php endif; ?>

    <div class="card" style="padding:16px;margin:16px 0;">
      <h3 style="margin-top:0;"><?= $article_edit ? 'Редакция на статия' : 'Нова статия' ?></h3>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="article_save">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= h($article_edit['id'] ?? '') ?>">

        <label>Заглавие *</label>
        <input id="title" name="title" required value="<?= h($article_edit['title'] ?? '') ?>" style="width:100%;margin:6px 0 12px;">

        <label>Slug (автоматично, може да редактираш)</label>
        <input id="slug" name="slug" value="<?= h($article_edit['slug'] ?? '') ?>" style="width:100%;margin:6px 0 12px;">

        <label>SEO Title (30-60 символа)</label>
        <input id="seo_title" name="seo_title" value="<?= h($article_edit['seo_title'] ?? '') ?>" style="width:100%;margin:6px 0 12px;">

        <label>Focus keyword</label>
        <input id="focus_keyword" name="focus_keyword" value="<?= h($article_edit['focus_keyword'] ?? '') ?>" style="width:100%;margin:6px 0 12px;">

        <label>Meta description (до 160 символа)</label>
        <input id="meta_description" name="meta_description" maxlength="160" value="<?= h($article_edit['meta_description'] ?? '') ?>" style="width:100%;margin:6px 0 12px;">

        <label>Кратко описание</label>
        <textarea name="excerpt" rows="3" style="width:100%;margin:6px 0 12px;"><?= h($article_edit['excerpt'] ?? '') ?></textarea>

        <label>Тагове (разделени със запетая)</label>
        <input name="tags" value="<?= h($article_edit['tags'] ?? '') ?>" style="width:100%;margin:6px 0 12px;">

        <label>Cover image</label>
        <input type="file" name="cover_image" accept="image/*" style="width:100%;margin:6px 0 12px;">

        <label>Cover image ALT</label>
        <input id="cover_alt" name="cover_alt" value="<?= h($article_edit['cover_alt'] ?? '') ?>" style="width:100%;margin:6px 0 12px;">
        <?php if (!empty($article_edit['cover_image'])): ?><p class="muted">Текущо: <code><?= h($article_edit['cover_image']) ?></code></p><?php endif; ?>

        <label>Съдържание (HTML) *</label>
        <textarea id="editor" name="content_html" rows="12" required style="width:100%;margin:6px 0 12px;"><?= h($article_edit['content_html'] ?? '') ?></textarea>

        <div id="seoPanel"></div>

        <label style="display:flex;align-items:center;gap:8px;margin:6px 0 12px;">
          <input type="checkbox" name="publish_now" value="1" <?= (($article_edit['status'] ?? '') === 'published') ? 'checked' : '' ?>>
          Публикувай
        </label>

        <button type="submit" class="btn" style="margin-right:8px;"><?= $article_edit ? 'Запази' : 'Създай' ?></button>
        <?php if ($article_edit): ?>
          <a class="btn btn-ghost" href="admin-83xk2.php?tab=articles">Откажи</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="card" style="padding:16px;">
      <h3 style="margin-top:0;">Списък (последни 20)</h3>
      <form method="post" style="margin-bottom:10px;">
        <input type="hidden" name="action" value="article_seed">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <button type="submit" class="btn btn-ghost">Seed тестова статия</button>
      </form>

      <?php foreach ($articles as $a): ?>
        <div style="border:1px solid #e6e6e6;border-radius:12px;padding:12px;margin:10px 0;">
          <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <strong><?= h($a['title'] ?? '') ?></strong>
            <span style="opacity:.8;">
              <?= (($a['status'] ?? 'draft') === 'published') ? 'Публикувана' : 'Чернова' ?>
              • <?= h(isset($a['created_at']) ? date('d.m.Y H:i', strtotime($a['created_at'])) : '') ?>
            </span>
          </div>
          <div style="opacity:.85;margin-top:6px;">
            Slug: <code><?= h($a['slug'] ?? '') ?></code>
          </div>
          <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn" href="admin-83xk2.php?tab=articles&article_edit=<?= h($a['id'] ?? '') ?>">Редактирай</a>
            <form method="post" action="admin-83xk2.php?tab=articles" onsubmit="return confirm('Да изтрия ли статията?')">
              <input type="hidden" name="action" value="article_delete">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <button class="btn danger" type="submit">Изтрий</button>
            </form>
            <?php if (($a['status'] ?? 'draft') === 'published'): ?>
              <form method="post" action="admin-83xk2.php?tab=articles">
                <input type="hidden" name="action" value="article_unpublish"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><button type="submit" class="btn btn-ghost">Скрий</button>
              </form>
            <?php else: ?>
              <form method="post" action="admin-83xk2.php?tab=articles">
                <input type="hidden" name="action" value="article_publish"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><button type="submit" class="btn">Публикувай</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
(function(){
  const title = document.getElementById('title');
  const seoTitle = document.getElementById('seo_title');
  const slug = document.getElementById('slug');
  const meta = document.getElementById('meta_description');
  const keyword = document.getElementById('focus_keyword');
  const coverAlt = document.getElementById('cover_alt');
  const editor = document.getElementById('editor');
  const panel = document.getElementById('seoPanel');

  function toSlug(v){
    const map={'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ж':'zh','з':'z','и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'sht','ъ':'a','ь':'','ю':'yu','я':'ya'};
    v=(v||'').toLowerCase().trim().replace(/[а-я]/g,ch=>map[ch]||ch);
    return v.replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,'');
  }

  function strip(html){
    const tmp = document.createElement('div');
    tmp.innerHTML = html || '';
    return (tmp.textContent || tmp.innerText || '').trim();
  }

  function countOccurrences(text, needle){
    if (!needle) return 0;
    const re = new RegExp(needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
    const m = text.match(re);
    return m ? m.length : 0;
  }

  function getContentHtml(){
    try {
      if (window.tinymce && tinymce.get('editor')) {
        return tinymce.get('editor').getContent();
      }
    } catch(e) {}
    return editor ? editor.value : '';
  }

  function run(){
    const kw = (keyword?.value || '').trim();
    const t = (title?.value || '').trim();
    const st = (seoTitle?.value || '').trim();
    const md = (meta?.value || '').trim();
    const sl = (slug?.value || '').trim();
    const alt = (coverAlt?.value || '').trim();
    const html = getContentHtml();
    const text = strip(html);

    const words = text ? text.split(/\s+/).filter(Boolean).length : 0;
    const kwCount = kw ? countOccurrences(text.toLowerCase(), kw.toLowerCase()) : 0;
    const density = words && kw ? (kwCount / words) * 100 : 0;

    const checks = [];
    checks.push({ ok: kw && t.toLowerCase().includes(kw.toLowerCase()), label: 'Ключовата дума е в заглавието (Title)' });
    checks.push({ ok: kw && (st || t).toLowerCase().includes(kw.toLowerCase()), label: 'Ключовата дума е в SEO Title' });
    checks.push({ ok: kw && md.toLowerCase().startsWith(kw.toLowerCase()), label: 'Meta Description започва с ключовата дума' });
    checks.push({ ok: md.length >= 120 && md.length <= 160, label: 'Meta Description 120–160 символа' });
    checks.push({ ok: (st || t).length >= 30 && (st || t).length <= 60, label: 'SEO Title 30–60 символа' });
    checks.push({ ok: sl.length >= 5, label: 'Slug е попълнен' });
    checks.push({ ok: words >= 600, label: 'Текстът е минимум 600 думи' });
    checks.push({ ok: /<h2\b/i.test(html), label: 'Има поне 1 H2' });
    checks.push({ ok: kw ? new RegExp(`<h2[^>]*>[^<]*${kw}[^<]*</h2>`, 'i').test(html) : false, label: 'Ключовата дума присъства в H2 (препоръчително)' });
    checks.push({ ok: /<a\s+[^>]*href="https?:\/\//i.test(html), label: 'Има външен линк' });
    checks.push({ ok: /<a\s+[^>]*href="(?!https?:\/\/)/i.test(html), label: 'Има вътрешен линк' });
    checks.push({ ok: alt.length >= 3, label: 'Cover image ALT е попълнен' });
    checks.push({ ok: kw ? (density >= 0.5 && density <= 2.5) : false, label: 'Keyword density ~0.5%–2.5% (ориентир)' });

    const max = checks.length;
    const passed = checks.filter(x => x.ok).length;
    const score = Math.round((passed / max) * 100);

    if (panel) {
      panel.innerHTML = `
        <div style="border:1px solid #e7eef9;border-radius:14px;padding:12px 14px;background:#fbfcff;margin-bottom:12px;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <strong>SEO Score (RankMath-style): ${score}%</strong>
            <span style="color:#556070;font-size:12px;">Думи: ${words} · KW: ${kwCount} · Density: ${density.toFixed(2)}%</span>
          </div>
          <ul style="margin:10px 0 0;padding-left:18px;">
            ${checks.map(c => `<li style="margin:6px 0;color:${c.ok ? 'green' : 'crimson'};">${c.label}</li>`).join('')}
          </ul>
        </div>
      `;
    }
  }

  if (title && slug) {
    title.addEventListener('input',()=>{ if(!slug.dataset.touched){ slug.value=toSlug(title.value); run(); } });
    slug.addEventListener('input',()=>{ slug.dataset.touched='1'; run(); });
  }

  ['input','change'].forEach(ev=>{
    title?.addEventListener(ev, run);
    seoTitle?.addEventListener(ev, run);
    meta?.addEventListener(ev, run);
    keyword?.addEventListener(ev, run);
    coverAlt?.addEventListener(ev, run);
    editor?.addEventListener(ev, run);
  });

  document.addEventListener('tinymce-editor-init', run);
  setInterval(run, 1200);
  run();
})();
</script>

</body>
</html>
