<?php
require_once __DIR__ . '/includes/blog_helpers.php';

$pdo = db();
$base = base_url();

$authorName = 'Магос ЕООД';
$authorDesc = 'Екип по счетоводство, ДДС и ТРЗ. Публикуваме практични материали, срокове, казуси и актуални промени.';
$authorUrl = $base . '/author.php';

$stmt = $pdo->prepare("
  SELECT slug, title, excerpt, published_at
  FROM posts
  WHERE status='published'
  ORDER BY published_at DESC, updated_at DESC
  LIMIT 12
");
$stmt->execute();
$posts = $stmt->fetchAll();

$authorJsonLd = [
  '@context' => 'https://schema.org',
  '@type' => 'Organization',
  'name' => $authorName,
  'url' => $base . '/',
  'sameAs' => [],
];
?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Автор | <?= h($authorName) ?></title>
  <meta name="description" content="<?= h($authorDesc) ?>">
  <link rel="canonical" href="<?= h($authorUrl) ?>">
  <script type="application/ld+json"><?= json_encode($authorJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body>
  <div class="wrap" style="max-width:860px; margin:0 auto; padding:24px 16px 60px;">
    <div class="box" style="border:1px solid #e7eef9; border-radius:18px; padding:18px; box-shadow:0 10px 30px rgba(0,0,0,.06);">
      <h1 style="margin:0 0 6px; font-size:28px;"><?= h($authorName) ?></h1>
      <div class="muted" style="color:#556070;"><?= h($authorDesc) ?></div>
      <div class="muted" style="color:#556070; margin-top:10px;">
        Контакт: <a href="/#contact">форма за контакт</a> · <a href="/statii/">към статиите</a>
      </div>
    </div>

    <h2 style="margin-top:18px;">Последни статии</h2>
    <div class="list" style="margin-top:16px; display:grid; gap:10px;">
      <?php foreach ($posts as $p): ?>
        <a class="item" href="/blog/<?= h((string)$p['slug']) ?>" style="border:1px solid #e7eef9; border-radius:14px; padding:12px 14px; text-decoration:none; color:#111;">
          <strong><?= h((string)$p['title']) ?></strong>
          <div class="meta" style="font-size:12px; color:#556070; margin-top:4px;"><?= h($p['published_at'] ? date('d.m.Y', strtotime((string)$p['published_at'])) : '') ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
