<?php
require_once __DIR__ . '/../includes/db.php';

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$articles = [];
$total = 0;

try {
  $pdo = posts_pdo(true);
  $where = "status = 'published'";
  $params = [];

  if ($q !== '') {
    $where .= ' AND (title LIKE :q OR excerpt LIKE :q)';
    $params[':q'] = '%' . $q . '%';
  }

  $countStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE {$where}");
  $countStmt->execute($params);
  $total = (int)$countStmt->fetchColumn();

  $pages = max(1, (int)ceil($total / $perPage));
  if ($page > $pages) $page = $pages;

  $sql = "SELECT * FROM posts WHERE {$where} ORDER BY published_at DESC, id DESC LIMIT :limit OFFSET :offset";
  $stmt = $pdo->prepare($sql);
  foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
  }
  $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
  $stmt->execute();
  $articles = $stmt->fetchAll();
} catch (Throwable $e) {
  $pages = 1;
}

$pages = $pages ?? 1;
?>
<!doctype html>
<html lang="bg">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Статии | Магос ЕООД</title>
    <meta
      name="description"
      content="Статии и полезни материали за ДДС, счетоводство и ТРЗ от Магос ЕООД."
    />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="/styles.css" />
    <link rel="canonical" href="https://magos.bg/statii/" />

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

    <main>
      <section class="hero" style="min-height: 360px;">
        <div class="hero-overlay"></div>
        <div class="hero-content">
          <p class="tag">ПОЛЕЗНИ МАТЕРИАЛИ</p>
          <h1>Статии</h1>
          <p class="subtitle">
            Практични обяснения за ДДС, счетоводство и ТРЗ. Кратко, ясно и с фокус върху реални казуси.
          </p>
          <div class="hero-actions">
            <a class="btn" href="/">← Обратно към началото</a>
            <a class="btn btn-ghost" href="/#contact">Запитване</a>
          </div>
        </div>
      </section>

      <section class="cards" style="padding-top: 24px;">
        <article class="card" style="grid-column: 1 / -1;">
          <form method="get" action="/statii/" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <input
              type="text"
              name="q"
              placeholder="Търси статия..."
              value="<?= h($q) ?>"
              style="flex:1 1 260px; border:1px solid #d9e1ef; border-radius:10px; padding:10px 12px; font:inherit;"
            >
            <button class="btn" type="submit">Търси</button>
          </form>
        </article>

        <?php if (!$articles): ?>
          <article class="card service-card">
            <div>
              <h3>Няма намерени статии</h3>
              <p>Промени ключовата дума или опитай отново по-късно.</p>
            </div>
          </article>
        <?php endif; ?>

        <?php foreach ($articles as $a): ?>
          <?php $href = '/blog/' . rawurlencode((string)$a['slug']); ?>
          <article class="card service-card">
            <div>
              <p class="tag"><?= h(date('d.m.Y', strtotime((string)($a['published_at'] ?? $a['created_at'] ?? 'now')) ?: time())) ?></p>
              <h3><?= h($a['title'] ?? '') ?></h3>
              <p><?= h(($a['excerpt'] ?? '') ?: 'Прочетете статията за повече подробности.') ?></p>
              <div class="hero-actions" style="justify-content:flex-start; gap:12px; margin-top:10px;">
                <a class="btn" href="<?= h($href) ?>">Прочети</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </section>

      <?php if ($pages > 1): ?>
      <section class="cta-strip" style="justify-content:center; gap:10px; flex-wrap:wrap;">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a class="btn<?= $i === $page ? '' : ' btn-ghost' ?>" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>"><?= $i ?></a>
        <?php endfor; ?>
      </section>
      <?php endif; ?>

      <section class="cta-strip">
        <p>Пишете ни и ще получите оферта до 24 часа.</p>
        <a class="btn" href="/#contact">Запитване</a>
      </section>
    </main>

    <footer>
      <p>© <span id="year">2026</span> Магос ЕООД • Тел.: +359 893 208 961 • Всички права запазени.</p>
    </footer>

    <script src="/script.js"></script>
    <script>
      try { document.getElementById('year').textContent = new Date().getFullYear(); } catch(e) {}
    </script>
  </body>
</html>
