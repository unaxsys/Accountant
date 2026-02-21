<?php
declare(strict_types=1);

const BLOG_INDEX_DATA_FILE = __DIR__ . '/../data/posts.json';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function load_blog_posts(): array
{
    if (!is_file(BLOG_INDEX_DATA_FILE)) {
        return [];
    }

    $raw = file_get_contents(BLOG_INDEX_DATA_FILE);
    if ($raw === false) {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $posts = array_values(array_filter($decoded, static fn($post) => is_array($post) && !empty($post['slug']) && !empty($post['title'])));
    usort($posts, static function (array $a, array $b): int {
        return strtotime((string)($b['date'] ?? '')) <=> strtotime((string)($a['date'] ?? ''));
    });

    return $posts;
}

function post_date(array $post): string
{
    $ts = strtotime((string)($post['date'] ?? ''));
    return $ts !== false ? date('d.m.Y', $ts) : date('d.m.Y');
}

$posts = load_blog_posts();
?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Блог | magos.bg</title>
  <meta name="description" content="SEO-friendly блог на magos.bg със статии за счетоводство, ДДС и ТРЗ.">
  <link rel="canonical" href="https://magos.bg/blog/">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/styles.css">
</head>
<body>
<main class="blog-layout">
  <header class="blog-header">
    <p class="tag">Блог</p>
    <h1>Статии за счетоводство, ДДС и ТРЗ</h1>
    <p class="subtitle">Практични материали с фокус върху реални казуси за собственици на бизнес и екипи.</p>
  </header>

  <section class="blog-grid" aria-label="Списък със статии">
    <?php foreach ($posts as $post): ?>
      <a class="blog-card" href="/blog/<?= e((string)$post['slug']) ?>">
        <p class="blog-card__date"><?= e(post_date($post)) ?></p>
        <h2><?= e((string)$post['title']) ?></h2>
        <p><?= e((string)($post['excerpt'] ?? '')) ?></p>
      </a>
    <?php endforeach; ?>
  </section>
</main>
</body>
</html>
