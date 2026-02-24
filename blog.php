<?php
declare(strict_types=1);

const BLOG_DATA_FILE = __DIR__ . '/data/posts.json';
const BLOG_BASE_URL = 'https://magos.bg';

require_once __DIR__ . '/includes/db.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function load_posts(): array
{
    try {
        $stmt = posts_pdo()->query("SELECT * FROM posts WHERE status='published' ORDER BY published_at DESC, created_at DESC");
        $rows = $stmt->fetchAll();

        if (is_array($rows) && $rows !== []) {
            return array_map(static function (array $row): array {
                $tags = trim((string)($row['tags'] ?? ''));
                $row['tags'] = $tags === ''
                    ? []
                    : array_values(array_filter(array_map('trim', explode(',', $tags)), static fn(string $tag): bool => $tag !== ''));

                return $row;
            }, $rows);
        }
    } catch (Throwable $e) {
        error_log('blog.php DB fallback: ' . $e->getMessage());
        // Fallback to JSON, to keep the page available if DB is temporarily unavailable.
    }

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

    $posts = array_values(array_filter($decoded, static fn($post) => is_array($post) && !empty($post['slug'])));
    usort($posts, static fn(array $a, array $b): int => post_timestamp($b) <=> post_timestamp($a));

    return $posts;
}

function is_valid_slug(string $slug): bool
{
    return (bool)preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
}

function request_slug(): string
{
    $slug = trim((string)($_GET['slug'] ?? ''));
    if ($slug !== '') {
        return $slug;
    }

    $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $path = trim($path, '/');
    if ($path === '') {
        return '';
    }

    $parts = explode('/', $path);
    $last = end($parts);
    if (!is_string($last) || $last === 'blog' || $last === 'blog.php') {
        return '';
    }

    return $last;
}

function post_timestamp(array $post): int
{
    $date = (string)($post['published_at'] ?? $post['date'] ?? '');
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

function cover_image_url(array $post): string
{
    $coverImage = (string)($post['cover_image'] ?? '');
    if ($coverImage === '') {
        return '';
    }

    if (str_starts_with($coverImage, 'http://') || str_starts_with($coverImage, 'https://')) {
        return $coverImage;
    }

    return BLOG_BASE_URL . '/' . ltrim($coverImage, '/');
}

function render_site_header(string $title, string $description, string $canonicalUrl, string $ogType = 'website', string $ogImage = ''): void
{
    ?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($description) ?>">
  <link rel="canonical" href="<?= e($canonicalUrl) ?>">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:url" content="<?= e($canonicalUrl) ?>">
  <?php if ($ogImage !== ''): ?>
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <?php endif; ?>
  <meta property="og:type" content="<?= e($ogType) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="/tab-logo.png">
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
    <a href="/blog.php">Статии</a>
    <a href="/#contact">Контакт</a>
  </nav>
  <a class="btn btn-small" href="/#contact">Запитване</a>
</header>
<?php
}

function render_site_footer(): void
{
    ?>
<script src="/script.js" defer></script>
</body>
</html>
<?php
}

$posts = load_posts();
$slug = request_slug();

if ($slug === '') {
    render_site_header(
        'Блог | Магос ЕООД',
        'Практични статии за счетоводство, ДДС и ТРЗ от Магос ЕООД.',
        BLOG_BASE_URL . '/blog/',
        'website'
    );
    ?>
<main>
  <section class="hero">
    <div class="hero-overlay"></div>
    <div class="hero-content">
      <p class="tag">СЧЕТОВОДСТВО ОТ НОВО ПОКОЛЕНИЕ</p>
      <h1>Финансова яснота за смелия бизнес.</h1>
      <p class="subtitle">Практични статии за ДДС, счетоводство и ТРЗ с ясен език и реални примери.</p>
      <div class="hero-actions">
        <a class="btn" href="#blog-list">Виж статиите</a>
        <a href="/#contact" class="btn btn-ghost">Вземи оферта до 24 часа</a>
      </div>
    </div>
  </section>

  <section class="stats">
    <article><h3>Оферта до 24 ч.</h3><p>бърз старт без губене на време</p></article>
    <article><h3>Ясни срокове</h3><p>контрол на ДДС и задължения към НАП</p></article>
    <article><h3>Персонален контакт</h3><p>директна връзка, без “прехвърляния”</p></article>
  </section>

  <section class="blog-layout" id="blog-list">
    <div class="blog-grid">
      <?php foreach ($posts as $p): ?>
        <?php
          $url = '/blog/' . e((string)$p['slug']);
          $date = date('d.m.Y', post_timestamp($p));
          $excerpt = (string)($p['excerpt'] ?? '');
          $image = cover_image_url($p);
        ?>
        <a class="blog-card" href="<?= $url ?>">
          <?php if ($image !== ''): ?>
            <img src="<?= e($image) ?>" alt="<?= e((string)($p['title'] ?? 'Статия')) ?>" style="width:100%;height:180px;object-fit:cover;border-radius:12px;margin-bottom:12px;">
          <?php endif; ?>
          <p class="blog-card__date"><?= e($date) ?></p>
          <h2><?= e((string)($p['title'] ?? 'Статия')) ?></h2>
          <p><?= e($excerpt) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
</main>
<?php
    render_site_footer();
    exit;
}

if (!is_valid_slug($slug)) {
    http_response_code(404);
    echo 'Невалиден адрес на статия.';
    exit;
}

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

$post = $currentPost;
$title = (string)($post['seo_title'] ?? $post['title'] ?? 'Статия');
$metaDescription = (string)($post['meta_description'] ?? $post['excerpt'] ?? '');
$canonicalUrl = BLOG_BASE_URL . '/blog/' . (string)($post['slug'] ?? '');
$ogImage = cover_image_url($post);
$excerpt = (string)($post['excerpt'] ?? '');
$publishedTs = post_timestamp($post);
$contentHtml = (string)($post['content_html'] ?? '<p>Съдържанието скоро ще бъде добавено.</p>');
$relatedPosts = find_related_posts($posts, $post, 3);

render_site_header($title, $metaDescription, $canonicalUrl, 'article', $ogImage);
?>
<main class="blog-layout blog-layout--article">
  <a href="/blog.php" class="back-link">← Към всички статии</a>

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
<?php render_site_footer(); ?>
