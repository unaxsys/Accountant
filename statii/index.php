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
  return 0;
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

$raw = file_exists($DATA_FILE) ? file_get_contents($DATA_FILE) : '[]';
$articles = json_decode($raw, true);
$articles = is_array($articles) ? $articles : [];

$articles = array_values(array_filter($articles, fn($a) => is_array($a) && article_published($a)));
usort($articles, fn($a, $b) => article_ts($b) <=> article_ts($a));

$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
  $articles = array_values(array_filter($articles, fn($a) =>
    stripos((string)($a['title'] ?? ''), $q) !== false || stripos((string)($a['excerpt'] ?? ''), $q) !== false
  ));
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$total = count($articles);
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) $page = $pages;
$articles = array_slice($articles, ($page - 1) * $perPage, $perPage);
?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Статии | Магос ЕООД</title>
  <meta name="description" content="Статии и полезни материали за ДДС, счетоводство и ТРЗ от Магос ЕООД." />
  <link rel="stylesheet" href="/styles.css" />
</head>
<body>
  <main class="blog-page">
    <section class="blog-head">
      <h1>Статии</h1>
      <p>Практични обяснения за ДДС, счетоводство и ТРЗ.</p>
      <form method="get" class="blog-search" action="/statii/">
        <input type="text" name="q" placeholder="Търси статия..." value="<?= h($q) ?>">
        <button type="submit">Търси</button>
      </form>
    </section>

    <section class="blog-grid" aria-live="polite">
      <?php if (!$articles): ?>
        <article class="blog-card"><h3>Няма намерени статии</h3><p>Промени ключовата дума или опитай отново по-късно.</p></article>
      <?php endif; ?>

      <?php foreach ($articles as $a): ?>
        <?php $slug = article_slug($a); ?>
        <article class="blog-card">
          <div class="blog-meta"><?= h(date('d.m.Y', article_ts($a) ?: time())) ?></div>
          <h2><a href="/statii/<?= rawurlencode($slug) ?>"><?= h($a['title'] ?? '') ?></a></h2>
          <p><?= h(($a['excerpt'] ?? '') ?: 'Прочетете статията за повече подробности.') ?></p>
          <a class="blog-read-more" href="/statii/<?= rawurlencode($slug) ?>">Прочети повече →</a>
        </article>
      <?php endforeach; ?>
    </section>

    <?php if ($pages > 1): ?>
      <nav class="blog-pagination" aria-label="Страници">
        <?php for($i = 1; $i <= $pages; $i++): ?>
          <a href="?page=<?= $i ?>&q=<?= urlencode($q) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  </main>
</body>
</html>
