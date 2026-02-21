<?php
$DATA_FILE = __DIR__ . '/../articles.json';

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function article_ts(array $article): int {
  $raw = $article['created_at'] ?? $article['date'] ?? null;
  if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
    return (int)$raw;
  }
  if (is_string($raw) && $raw !== '') {
    $parsed = strtotime($raw);
    if ($parsed !== false) return $parsed;
  }
  return time();
}

function article_published(array $article): bool {
  if (array_key_exists('published', $article)) return !empty($article['published']);
  if (array_key_exists('is_published', $article)) return !empty($article['is_published']);
  return true;
}

function article_slug(array $article): string {
  if (!empty($article['slug'])) return trim((string)$article['slug']);
  if (!empty($article['url'])) return trim((string)basename((string)$article['url']), '/');
  return '';
}

$slug = trim((string)($_GET['slug'] ?? ''));
$articles = file_exists($DATA_FILE) ? json_decode(file_get_contents($DATA_FILE), true) : [];
$articles = is_array($articles) ? $articles : [];

$article = null;
foreach ($articles as $a) {
  if (is_array($a) && article_published($a) && article_slug($a) === $slug) {
    $article = $a;
    break;
  }
}

if (!$article) {
  http_response_code(404);
  ?><!doctype html><html lang="bg"><head><meta charset="utf-8"><title>Статията не е намерена</title><link rel="stylesheet" href="/styles.css"></head><body><main class="blog-article"><a class="back-link" href="/statii/">← Назад към всички статии</a><h1>Статията не е намерена</h1></main></body></html><?php
  exit;
}

$title = (string)($article['title'] ?? 'Статия');
$excerpt = trim((string)($article['excerpt'] ?? ''));
$content = (string)($article['content'] ?? $article['excerpt'] ?? '');
$publishedAt = article_ts($article);
?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h($title) ?> | Магос ЕООД</title>
  <meta name="description" content="<?= h($excerpt !== '' ? $excerpt : $title) ?>">
  <link rel="canonical" href="https://magos.bg/statii/<?= rawurlencode($slug) ?>">
  <link rel="stylesheet" href="/styles.css" />
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": <?= json_encode($title, JSON_UNESCAPED_UNICODE) ?>,
    "datePublished": "<?= date('c', $publishedAt) ?>",
    "author": {
      "@type": "Organization",
      "name": "Магос ЕООД"
    }
  }
  </script>
</head>
<body>
  <main class="blog-article">
    <a href="/statii/" class="back-link">← Назад към всички статии</a>
    <p class="blog-meta"><?= h(date('d.m.Y', $publishedAt)) ?></p>
    <h1><?= h($title) ?></h1>
    <?php if ($excerpt !== ''): ?><p class="blog-lead"><?= h($excerpt) ?></p><?php endif; ?>
    <article class="blog-content"><?= nl2br(h($content)) ?></article>
  </main>
</body>
</html>
