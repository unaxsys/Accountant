<?php
require_once __DIR__ . '/../includes/blog_helpers.php';

function normalize_heading_text(string $value): string
{
    $normalized = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $normalized = str_replace(["\xC2\xA0", '–', '—'], [' ', '-', '-'], $normalized);
    $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

    return mb_strtolower(trim($normalized), 'UTF-8');
}

function strip_leading_duplicate_heading(string $contentHtml, string $title): string
{
    $pattern = '/^\s*<h([1-6])\b[^>]*>(.*?)<\/h\1>\s*/isu';
    if (!preg_match($pattern, $contentHtml, $matches)) {
        return $contentHtml;
    }

    $headingText = normalize_heading_text((string)($matches[2] ?? ''));
    $titleText = normalize_heading_text($title);

    if ($headingText !== $titleText) {
        return $contentHtml;
    }

    return preg_replace($pattern, '', $contentHtml, 1) ?? $contentHtml;
}

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

$contentHtml = strip_leading_duplicate_heading((string)$post['content_html'], (string)$post['title']);
$crumbs = breadcrumbs_for_post($post);
$faq = extract_faq_from_html($contentHtml);

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
<a href="/" class="corner-logo" aria-label="Магос ЕООД начало">
  <span class="logo-mark" aria-hidden="true">
    <img src="/magos-logo.png" alt="Магос ЕООД" onerror="this.onerror=null;this.src='/magos-logo.svg';">
  </span>
</a>

<header class="site-header" id="site-header">
  <button class="menu-toggle" id="menu-toggle" type="button" aria-label="Отвори меню" aria-expanded="false">☰</button>
  <nav class="main-nav" aria-label="Главно меню">
    <a href="/#services">Услуги</a>
    <a href="/#about">За нас</a>
    <a href="/statii/">Статии</a>
    <a href="/#contact">Контакт</a>
  </nav>
  <a class="btn btn-small" href="/#contact">Запитване</a>
</header>

<main class="blog-layout blog-layout--article">
  <section class="hero">
    <div class="hero-overlay"></div>
    <div class="hero-content">
      <p class="tag">СЧЕТОВОДСТВО ОТ НОВО ПОКОЛЕНИЕ</p>
      <h1><?= h((string)$post['title']) ?></h1>
      <p class="subtitle">Практични статии за ДДС, счетоводство и ТРЗ с ясен език и реални примери.</p>
      <div class="hero-actions">
        <a class="btn" href="/statii/">← Към всички статии</a>
        <a href="/#contact" class="btn btn-ghost">Вземи оферта до 24 часа</a>
      </div>
    </div>
  </section>

  <a href="/statii/" class="back-link">← Към всички статии</a>

  <article class="blog-article-card">
    <nav class="crumbs" aria-label="Breadcrumb" style="font-size:13px; color:#556070; margin-bottom:10px;">
      <?php foreach ($crumbs as $i => $c): ?>
        <?php if ($i > 0): ?> / <?php endif; ?>
        <?php $crumbHref = ((string)$c['name'] === 'Статии') ? '/statii/' : (string)(parse_url((string)$c['url'], PHP_URL_PATH) ?: $c['url']); ?>
        <a href="<?= h($crumbHref) ?>" style="color:#556070; text-decoration:none;"><?= h((string)$c['name']) ?></a>
      <?php endforeach; ?>
    </nav>

    <?php if ($ogImage): ?>
      <img src="<?= h($ogImage) ?>" alt="<?= h((string)($post['cover_alt'] ?: $post['title'])) ?>" style="width:100%; height:320px; object-fit:cover; display:block; background:#f3f6ff; border-radius:14px;">
    <?php else: ?>
      <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1200' height='600'%3E%3Crect width='1200' height='600' fill='%23f3f6ff'/%3E%3C/svg%3E" alt="<?= h((string)$post['title']) ?>" style="width:100%; height:320px; object-fit:cover; display:block; background:#f3f6ff; border-radius:14px;">
    <?php endif; ?>

    <p class="blog-date" style="margin-top:14px;">Публикувано: <?= h($post['published_at'] ? date('d.m.Y', strtotime((string)$post['published_at'])) : date('d.m.Y')) ?></p>

    <?php if (!empty($post['excerpt'])): ?>
      <p class="blog-lead"><?= h((string)$post['excerpt']) ?></p>
    <?php endif; ?>

    <article class="blog-content">
      <?= $contentHtml ?>
    </article>

    <?php if (!empty($faq)): ?>
      <div class="faqHint" style="margin-top:18px; padding:12px 14px; border:1px solid #e7eef9; border-radius:14px; background:#fbfcff; color:#556070; font-size:13px;">Открит е FAQ блок в статията – автоматично добавихме FAQ schema (rich results).</div>
    <?php endif; ?>

    <a href="/statii/" class="back-link" style="margin-top:14px;">← Назад към статиите</a>
  </article>

  <section class="cta-strip">
    <p>Пишете ни и ще получите оферта до 24 часа.</p>
    <a class="btn" href="/#contact">Запитване</a>
  </section>
</main>

<script src="/script.js" defer></script>
</body>
</html>
