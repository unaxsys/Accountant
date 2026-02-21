<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$slug = trim((string)($_GET['slug'] ?? ''));

try {
  $pdo = posts_pdo(true);
  $stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = :slug AND status='published' LIMIT 1");
  $stmt->execute([':slug' => $slug]);
  $post = $stmt->fetch();
} catch (Throwable $e) {
  $post = false;
}

if (!$post) {
  http_response_code(404);
  ?><!doctype html><html lang="bg"><head><meta charset="utf-8"><title>Статията не е намерена</title><link rel="stylesheet" href="/styles.css"></head><body><main class="blog-article"><a class="back-link" href="/statii/">← Назад към статии</a><h1>Статията не е намерена</h1></main></body></html><?php
  exit;
}

$title = (string)$post['title'];
$description = trim((string)($post['meta_description'] ?: $post['excerpt'] ?: $title));
$canonical = rtrim(SITE_URL, '/') . '/statii/' . rawurlencode((string)$post['slug']);
$publishedTs = strtotime((string)($post['published_at'] ?? $post['created_at'] ?? 'now')) ?: time();
$cover = trim((string)($post['cover_image'] ?? ''));
$coverAbs = $cover !== '' ? rtrim(SITE_URL, '/') . $cover : '';

?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h($title) ?> | Магос ЕООД</title>
  <meta name="description" content="<?= h($description) ?>">
  <link rel="canonical" href="<?= h($canonical) ?>">
  <meta property="og:type" content="article">
  <meta property="og:title" content="<?= h($title) ?>">
  <meta property="og:description" content="<?= h($description) ?>">
  <meta property="og:url" content="<?= h($canonical) ?>">
  <?php if ($coverAbs !== ''): ?><meta property="og:image" content="<?= h($coverAbs) ?>"><?php endif; ?>
  <link rel="stylesheet" href="/styles.css" />
</head>
<body>
  <main class="blog-article">
    <a href="/statii/" class="back-link">← Назад към статии</a>
    <p class="blog-meta"><?= h(date('d.m.Y', $publishedTs)) ?></p>
    <h1><?= h($title) ?></h1>
    <?php if ($cover !== ''): ?><p><img src="<?= h($cover) ?>" alt="<?= h($title) ?>" style="max-width:100%;border-radius:14px;"></p><?php endif; ?>
    <?php if (!empty($post['excerpt'])): ?><p class="blog-lead"><?= h($post['excerpt']) ?></p><?php endif; ?>
    <article class="blog-content"><?= (string)$post['content_html'] ?></article>
  </main>
</body>
</html>
