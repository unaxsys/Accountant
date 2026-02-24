<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

const BLOG_BASE_URL = 'https://magos.bg';

function e(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_valid_slug(string $slug): bool {
  return (bool)preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
}

function post_timestamp_from_row(array $row): int {
  $date = (string)($row['published_at'] ?? '');
  if ($date === '') $date = (string)($row['created_at'] ?? '');
  $ts = strtotime($date);
  return $ts !== false ? $ts : time();
}

function parse_tags(?string $tags): array {
  if ($tags === null) return [];
  $parts = preg_split('/[,\s]+/', $tags) ?: [];
  $out = [];
  foreach ($parts as $t) {
    $t = trim(mb_strtolower($t));
    if ($t !== '') $out[] = $t;
  }
  return array_values(array_unique($out));
}

function find_related_posts(PDO $pdo, array $currentPost, int $limit = 3): array {
  $currentTags = parse_tags((string)($currentPost['tags'] ?? ''));
  if ($currentTags === []) return [];

  $likes = [];
  $params = [];
  foreach ($currentTags as $t) {
    $likes[] = "tags LIKE ?";
    $params[] = '%' . $t . '%';
  }
  $params[] = (string)($currentPost['slug'] ?? '');

  $sql = "
    SELECT title, slug, excerpt, seo_title, tags, published_at, created_at
    FROM articles
    WHERE status='published'
      AND (" . implode(' OR ', $likes) . ")
      AND slug <> ?
    ORDER BY COALESCE(published_at, created_at) DESC
    LIMIT " . (int)$limit . "
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll() ?: [];
}

$slug = trim((string)($_GET['slug'] ?? ''));
if (!is_valid_slug($slug)) {
  http_response_code(404);
  echo 'Невалиден адрес на статия.';
  exit;
}

$pdo = posts_pdo(true);

$stmt = $pdo->prepare("SELECT * FROM articles WHERE slug=? AND status='published' LIMIT 1");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
  http_response_code(404);
  echo 'Статията не е намерена.';
  exit;
}

$BASE_URL = BLOG_BASE_URL;
$title = (string)(($post['seo_title'] ?? '') ?: ($post['title'] ?? ''));
$metaDescription = (string)($post['meta_description'] ?? '');
$canonicalUrl = $BASE_URL . '/blog/' . (string)$post['slug'];

$cover = (string)($post['cover_image'] ?? '');
$ogImage = $cover !== '' ? ($BASE_URL . '/' . ltrim($cover, '/')) : ($BASE_URL . '/assets/og-default.jpg');

$excerpt = (string)($post['excerpt'] ?? '');
$publishedTs = post_timestamp_from_row($post);
$contentHtml = (string)($post['content'] ?? '<p>Съдържанието скоро ще бъде добавено.</p>');

$relatedPosts = find_related_posts($pdo, $post, 3);
?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?></title>
  <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">

  <meta property="og:title" content="<?= htmlspecialchars($title) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
  <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
  <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
  <meta property="og:type" content="article">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/styles.css">
</head>
<body>
<main class="blog-layout blog-layout--article">
  <a href="/blog/" class="back-link">← Към всички статии</a>

  <article class="blog-article-card">
    <p class="blog-date"><?= e(date('d.m.Y', $publishedTs)) ?></p>
    <h1><?= e($title) ?></h1>
    <?php if ($excerpt !== ''): ?>
      <p class="blog-lead"><?= e($excerpt) ?></p>
    <?php endif; ?>

    <article class="blog-content"><?= $contentHtml ?></article>
  </article>

  <?php if ($relatedPosts !== []): ?>
    <section class="blog-related">
      <h2>Свързани статии</h2>
      <div class="blog-grid">
        <?php foreach ($relatedPosts as $related): ?>
          <a class="blog-card" href="/blog/<?= e((string)$related['slug']) ?>">
            <p class="blog-card__date"><?= e(date('d.m.Y', post_timestamp_from_row($related))) ?></p>
            <h3><?= e((string)($related['title'] ?? 'Статия')) ?></h3>
            <p><?= e((string)($related['excerpt'] ?? '')) ?></p>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</main>
</body>
</html>