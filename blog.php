<?php
declare(strict_types=1);

const BLOG_DATA_FILE = __DIR__ . '/data/posts.json';
const BLOG_BASE_URL = 'https://magos.bg';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function load_posts(): array
{
    if (!is_file(BLOG_DATA_FILE)) {
        return [];
    }

    $raw = file_get_contents(BLOG_DATA_FILE);
    if ($raw === false) {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter($decoded, static fn($post) => is_array($post) && !empty($post['slug'])));
}

function is_valid_slug(string $slug): bool
{
    return (bool)preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
}

function post_timestamp(array $post): int
{
    $date = isset($post['date']) ? (string)$post['date'] : '';
    $ts = strtotime($date);
    return $ts !== false ? $ts : time();
}

function find_related_posts(array $allPosts, array $currentPost, int $limit = 3): array
{
    $currentTags = array_values(array_filter($currentPost['tags'] ?? [], 'is_string'));
    if ($currentTags === []) {
        return [];
    }

    $scores = [];
    foreach ($allPosts as $candidate) {
        if (($candidate['slug'] ?? '') === ($currentPost['slug'] ?? '')) {
            continue;
        }

        $candidateTags = array_values(array_filter($candidate['tags'] ?? [], 'is_string'));
        $common = array_intersect($currentTags, $candidateTags);
        if ($common === []) {
            continue;
        }

        $scores[] = [
            'score' => count($common),
            'date' => post_timestamp($candidate),
            'post' => $candidate,
        ];
    }

    usort($scores, static function (array $a, array $b): int {
        return [$b['score'], $b['date']] <=> [$a['score'], $a['date']];
    });

    return array_map(static fn($item) => $item['post'], array_slice($scores, 0, $limit));
}

$slug = trim((string)($_GET['slug'] ?? ''));
if (!is_valid_slug($slug)) {
    http_response_code(404);
    echo 'Невалиден адрес на статия.';
    exit;
}

$posts = load_posts();
$currentPost = null;

foreach ($posts as $post) {
    if (($post['slug'] ?? '') === $slug) {
        $currentPost = $post;
        break;
    }
}

if ($currentPost === null) {
    http_response_code(404);
    echo 'Статията не е намерена.';
    exit;
}

$title = (string)($currentPost['title'] ?? 'Статия');
$metaDescription = (string)($currentPost['meta_description'] ?? $currentPost['excerpt'] ?? $title);
$excerpt = (string)($currentPost['excerpt'] ?? '');
$publishedTs = post_timestamp($currentPost);
$contentHtml = (string)($currentPost['content_html'] ?? '<p>Съдържанието скоро ще бъде добавено.</p>');
$canonicalUrl = BLOG_BASE_URL . '/blog/' . rawurlencode($slug);
$ogImage = (string)($currentPost['cover_image'] ?? (BLOG_BASE_URL . '/magos-logo.png'));
$relatedPosts = find_related_posts($posts, $currentPost, 3);
?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> | magos.bg</title>
  <meta name="description" content="<?= e($metaDescription) ?>">
  <link rel="canonical" href="<?= e($canonicalUrl) ?>">

  <meta property="og:type" content="article">
  <meta property="og:site_name" content="magos.bg">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($metaDescription) ?>">
  <meta property="og:url" content="<?= e($canonicalUrl) ?>">
  <meta property="og:image" content="<?= e($ogImage) ?>">

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
            <p class="blog-card__date"><?= e(date('d.m.Y', post_timestamp($related))) ?></p>
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
