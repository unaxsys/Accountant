<?php
require_once __DIR__ . '/../includes/blog_helpers.php';

$pdo = db();
$base = base_url();

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM posts WHERE slug=? AND status='published' LIMIT 1");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$title = $post['seo_title'] ?: $post['title'];
$metaDescription = $post['meta_description'] ?: mb_substr(trim(strip_tags((string)$post['content_html'])), 0, 160, 'UTF-8');
$canonicalUrl = $base . '/blog/' . $post['slug'];

$ogImage = '';
if (!empty($post['cover_image'])) {
    $ogImage = $base . '/' . ltrim((string)$post['cover_image'], '/');
}

$crumbs = breadcrumbs_for_post($post);
$faq = extract_faq_from_html((string)$post['content_html']);

$publishedIso = $post['published_at'] ? date('c', strtotime((string)$post['published_at'])) : date('c');
$updatedIso = $post['updated_at'] ? date('c', strtotime((string)$post['updated_at'])) : $publishedIso;

$authorName = 'Магос ЕООД';
$authorUrl = $base . '/author.php';

$articleJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $post['title'],
    'description' => $metaDescription,
    'mainEntityOfPage' => $canonicalUrl,
    'datePublished' => $publishedIso,
    'dateModified' => $updatedIso,
    'author' => [
        '@type' => 'Organization',
        'name' => $authorName,
        'url' => $authorUrl,
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => $authorName,
        'url' => $base . '/',
    ],
];

if ($ogImage) {
    $articleJsonLd['image'] = [$ogImage];
}

$faqJsonLd = null;
if (!empty($faq)) {
    $mainEntity = [];
    foreach ($faq as $x) {
        $mainEntity[] = [
            '@type' => 'Question',
            'name' => $x['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $x['a'],
            ],
        ];
    }
    $faqJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $mainEntity,
    ];
}
?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <title><?= h((string)$title) ?></title>
  <meta name="description" content="<?= h((string)$metaDescription) ?>">
  <link rel="canonical" href="<?= h($canonicalUrl) ?>">

  <meta property="og:title" content="<?= h((string)$title) ?>">
  <meta property="og:description" content="<?= h((string)$metaDescription) ?>">
  <meta property="og:url" content="<?= h($canonicalUrl) ?>">
  <meta property="og:type" content="article">
  <?php if ($ogImage): ?>
    <meta property="og:image" content="<?= h($ogImage) ?>">
  <?php endif; ?>

  <meta name="twitter:card" content="<?= $ogImage ? 'summary_large_image' : 'summary' ?>">

  <script type="application/ld+json"><?= json_encode($articleJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script type="application/ld+json"><?= jsonld_breadcrumbs($crumbs) ?></script>
  <?php if ($faqJsonLd): ?>
    <script type="application/ld+json"><?= json_encode($faqJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <?php endif; ?>
  <link rel="stylesheet" href="/styles.css">
</head>
<body>
  <div class="wrap" style="max-width:860px; margin:0 auto; padding:22px 16px 60px;">
    <nav class="crumbs" aria-label="Breadcrumb" style="font-size:13px; color:#556070; margin-bottom:14px;">
      <?php foreach ($crumbs as $i => $c): ?>
        <?php if ($i > 0): ?> / <?php endif; ?>
        <a href="<?= h((string)(parse_url((string)$c['url'], PHP_URL_PATH) ?: $c['url'])) ?>" style="color:#556070; text-decoration:none;"><?= h((string)$c['name']) ?></a>
      <?php endforeach; ?>
    </nav>

    <div style="margin:0 0 14px;">
      <a href="/blog/" style="display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border:1px solid #d7e2f3; border-radius:12px; background:#fff; color:#1f3f75; font-weight:700; text-decoration:none; box-shadow:0 4px 12px rgba(0,0,0,.05);">← Назад към статиите</a>
    </div>

    <div class="hero" style="border:1px solid #e7eef9; border-radius:18px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,.07); background:#fff;">
      <?php if ($ogImage): ?>
        <img src="<?= h($ogImage) ?>" alt="<?= h((string)($post['cover_alt'] ?: $post['title'])) ?>" style="width:100%; height:320px; object-fit:cover; display:block; background:#f3f6ff;">
      <?php else: ?>
        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1200' height='600'%3E%3Crect width='1200' height='600' fill='%23f3f6ff'/%3E%3C/svg%3E" alt="<?= h((string)$post['title']) ?>" style="width:100%; height:320px; object-fit:cover; display:block; background:#f3f6ff;">
      <?php endif; ?>
    </div>

    <h1 style="font-size:34px; line-height:1.1; margin:16px 0 6px;"><?= h((string)$post['title']) ?></h1>
    <div class="meta" style="color:#556070; font-size:13px; margin-bottom:12px;">
      Публикувано: <?= h($post['published_at'] ? date('d.m.Y', strtotime((string)$post['published_at'])) : date('d.m.Y')) ?>
      · Автор: <a href="/author.php"><?= h($authorName) ?></a>
    </div>

    <article class="content" style="line-height:1.7; font-size:16px;">
      <?= $post['content_html'] ?>
    </article>

    <?php if (!empty($faq)): ?>
      <div class="faqHint" style="margin-top:18px; padding:12px 14px; border:1px solid #e7eef9; border-radius:14px; background:#fbfcff; color:#556070; font-size:13px;">Открит е FAQ блок в статията – автоматично добавихме FAQ schema (rich results).</div>
    <?php endif; ?>

    <div style="margin-top:18px;">
      <a href="/blog/" style="display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border:1px solid #d7e2f3; border-radius:12px; background:#fff; color:#1f3f75; font-weight:700; text-decoration:none; box-shadow:0 4px 12px rgba(0,0,0,.05);">← Назад към статиите</a>
    </div>

    <div class="authorbox" style="margin-top:28px; border-top:1px solid #e7eef9; padding-top:16px; display:flex; gap:12px; align-items:flex-start;">
      <div class="avatar" style="width:44px; height:44px; border-radius:12px; background:#f3f6ff; display:flex; align-items:center; justify-content:center; font-weight:800;">M</div>
      <div>
        <strong><a href="/author.php" style="color:#111;"><?= h($authorName) ?></a></strong><br>
        <span style="color:#556070;">Счетоводство, ДДС и ТРЗ. Практични насоки и актуални промени.</span>
      </div>
    </div>
  </div>
</body>
</html>
