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
$excerpt = trim((string)($post['excerpt'] ?? ''));
$description = trim((string)($post['meta_description'] ?: $excerpt ?: $title));
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

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
  />
  <link rel="stylesheet" href="/styles.css" />

  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-NBCQS8P4KP"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-NBCQS8P4KP');
  </script>
</head>
<body>
  <a href="/" class="corner-logo" aria-label="Магос ЕООД начало">
    <span class="logo-mark" aria-hidden="true">
      <img src="/magos-logo.png" alt="Магос ЕООД" onerror="this.onerror=null;this.src='/magos-logo.svg';" />
    </span>
  </a>

  <header class="site-header" id="site-header">
    <button class="menu-toggle" id="menu-toggle" type="button" aria-label="Отвори меню" aria-expanded="false">☰</button>

    <nav class="main-nav" aria-label="Главно меню">
      <a href="/#services">Услуги</a>
      <div class="nav-dropdown">
        <button type="button" class="dropdown-toggle" aria-expanded="false">Решения ▾</button>
        <div class="dropdown-menu">
          <a href="/#process">Как започваме</a>
          <a href="/#testimonials">Мнения</a>
          <a href="/#faq">FAQ</a>
        </div>
      </div>
      <a href="/#about">За нас</a>
      <a href="/statii/" aria-current="page">Статии</a>
      <a href="/#contact">Контакт</a>
    </nav>

    <a class="btn btn-small" href="/#contact">Запитване</a>
  </header>

  <aside class="mobile-drawer" id="mobile-drawer" aria-hidden="true">
    <div class="drawer-head">
      <strong>Меню</strong>
      <button class="drawer-close" id="drawer-close" aria-label="Затвори меню">✕</button>
    </div>
    <nav class="drawer-nav">
      <a href="/#services">Услуги</a>
      <a href="/#process">Как започваме</a>
      <a href="/#testimonials">Мнения</a>
      <a href="/#faq">FAQ</a>
      <a href="/#about">За нас</a>
      <a href="/statii/" aria-current="page">Статии</a>
      <a href="/#contact">Контакт</a>
    </nav>
  </aside>
  <div class="drawer-backdrop" id="drawer-backdrop" hidden></div>

  <main class="blog-layout blog-layout--article blog-article">
    <section class="hero" style="min-height: 360px;">
      <div class="hero-overlay"></div>
      <div class="hero-content">
        <p class="tag"><?= h(date('d.m.Y', $publishedTs)) ?></p>
        <h1><?= h($title) ?></h1>
        <?php if ($excerpt !== ''): ?><p class="subtitle"><?= h($excerpt) ?></p><?php endif; ?>
        <div class="hero-actions">
          <a class="btn" href="/statii/">← Назад към статии</a>
          <a class="btn btn-ghost" href="/#contact">Запитване</a>
        </div>
      </div>
    </section>

    <section class="cards" style="padding-top: 24px;">
      <article class="card service-card" style="grid-column: 1 / -1;">
        <?php if ($cover !== ''): ?><p><img src="<?= h($cover) ?>" alt="<?= h($title) ?>" style="max-width:100%;border-radius:14px;"></p><?php endif; ?>
        <div class="blog-content"><?= (string)$post['content_html'] ?></div>
      </article>
    </section>
  </main>

  <script src="/script.js"></script>
</body>
</html>
