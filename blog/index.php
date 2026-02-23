<?php
require_once __DIR__ . '/../includes/blog_helpers.php';

$pdo = db();
$base = base_url();

$q = trim((string)($_GET['q'] ?? ''));
$tag = trim((string)($_GET['tag'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = "status='published'";
$params = [];

if ($q !== '') {
    $where .= " AND (title LIKE ? OR excerpt LIKE ? OR content_html LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($tag !== '') {
    $where .= " AND tags LIKE ?";
    $params[] = '%' . $tag . '%';
}

$stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM posts WHERE {$where}");
$stmt->execute($params);
$total = (int)($stmt->fetch()['cnt'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare("
  SELECT id, slug, title, seo_title, meta_description, excerpt, content_html, cover_image, cover_alt, tags, published_at, updated_at
  FROM posts
  WHERE {$where}
  ORDER BY published_at DESC, updated_at DESC
  LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$posts = $stmt->fetchAll();

function page_url(int $p, string $q, string $tag): string {
    $args = [];
    if ($q !== '') $args['q'] = $q;
    if ($tag !== '') $args['tag'] = $tag;
    $args['page'] = $p;
    return '/blog/?' . http_build_query($args);
}
?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Блог | Магос ЕООД</title>
  <meta name="description" content="Практични статии за счетоводство, ДДС и ТРЗ от Магос ЕООД.">
  <link rel="canonical" href="<?= h($base . '/blog/') ?>">
  <meta property="og:title" content="Блог | Магос ЕООД">
  <meta property="og:description" content="Практични статии за счетоводство, ДДС и ТРЗ от Магос ЕООД.">
  <meta property="og:url" content="<?= h($base . '/blog/') ?>">
  <meta property="og:type" content="website">
  <link rel="stylesheet" href="/styles.css">
</head>
<body>
  <div class="wrap" style="max-width:1120px; margin:0 auto; padding:24px 16px 60px;">
    <div class="top" style="display:flex; gap:12px; align-items:flex-end; justify-content:space-between; flex-wrap:wrap;">
      <div>
        <h1 style="margin:0; font-size:28px;">Блог</h1>
        <div class="meta" style="font-size:12px; color:#5b677a;">Статии за ДДС, счетоводство и ТРЗ</div>
      </div>

      <form class="search" method="get" action="/blog/" style="display:flex; gap:8px; flex-wrap:wrap;">
        <input type="text" name="q" placeholder="Търси по дума…" value="<?= h($q) ?>" style="padding:10px 12px; border:1px solid #d9e1ef; border-radius:10px; min-width:260px;">
        <?php if ($tag !== ''): ?>
          <input type="hidden" name="tag" value="<?= h($tag) ?>">
        <?php endif; ?>
        <button type="submit" class="btn">Търси</button>
      </form>
    </div>

    <?php if ($tag !== ''): ?>
      <p>Филтър по таг: <strong><?= h($tag) ?></strong> · <a href="/blog/">махни филтъра</a></p>
    <?php endif; ?>

    <div class="grid" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:14px; margin-top:18px;">
      <?php foreach ($posts as $p): ?>
        <?php
          $img = $p['cover_image'] ? ($base . '/' . ltrim((string)$p['cover_image'], '/')) : '';
          $url = '/blog/' . $p['slug'];
          $date = $p['published_at'] ? date('d.m.Y', strtotime((string)$p['published_at'])) : '';
          $excerpt = trim((string)($p['excerpt'] ?? ''));
          if ($excerpt === '') $excerpt = mb_substr(trim(strip_tags((string)$p['content_html'])), 0, 140, 'UTF-8') . '…';
        ?>
        <article class="card" style="border:1px solid #e7eef9; border-radius:16px; overflow:hidden; background:#fff; box-shadow:0 6px 18px rgba(0,0,0,.06);">
          <?php if ($img): ?>
            <a href="<?= h($url) ?>"><img src="<?= h($img) ?>" alt="<?= h((string)($p['cover_alt'] ?? $p['title'])) ?>" style="width:100%; height:180px; object-fit:cover; display:block; background:#f3f6ff;"></a>
          <?php else: ?>
            <a href="<?= h($url) ?>"><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1200' height='600'%3E%3Crect width='1200' height='600' fill='%23f3f6ff'/%3E%3C/svg%3E" alt="<?= h((string)$p['title']) ?>" style="width:100%; height:180px; object-fit:cover; display:block; background:#f3f6ff;"></a>
          <?php endif; ?>
          <div class="c" style="padding:14px;">
            <div class="meta" style="font-size:12px; color:#5b677a; margin-bottom:6px;"><?= h($date) ?></div>
            <h2 style="font-size:18px; margin:6px 0 10px;"><a href="<?= h($url) ?>" style="color:#111;text-decoration:none;"><?= h((string)$p['title']) ?></a></h2>
            <p style="margin:0; color:#334155; line-height:1.45;"><?= h($excerpt) ?></p>

            <?php if (!empty($p['tags'])): ?>
              <div class="tags" style="margin-top:10px; display:flex; gap:6px; flex-wrap:wrap;">
                <?php foreach (array_slice(array_filter(array_map('trim', explode(',', (string)$p['tags']))), 0, 4) as $t): ?>
                  <a class="tag" href="/blog/?<?= h(http_build_query(['tag' => $t])) ?>" style="font-size:12px; border:1px solid #d9e1ef; border-radius:999px; padding:4px 8px; color:#334155; text-decoration:none;"><?= h($t) ?></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav class="pager" aria-label="Pagination" style="display:flex; gap:8px; justify-content:center; margin-top:22px; flex-wrap:wrap;">
        <?php if ($page > 1): ?>
          <a href="<?= h(page_url($page - 1, $q, $tag)) ?>">← Назад</a>
        <?php endif; ?>

        <?php
          $start = max(1, $page - 2);
          $end = min($totalPages, $page + 2);
          for ($i = $start; $i <= $end; $i++):
        ?>
          <?php if ($i === $page): ?>
            <span style="padding:8px 12px; border-radius:10px; background:#111; color:#fff;"><?= $i ?></span>
          <?php else: ?>
            <a href="<?= h(page_url($i, $q, $tag)) ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
          <a href="<?= h(page_url($page + 1, $q, $tag)) ?>">Напред →</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</body>
</html>
