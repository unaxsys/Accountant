<?php
declare(strict_types=1);
require_once __DIR__ . '/posts_lib.php';

header('Content-Type: application/xml; charset=UTF-8');

$base = rtrim(defined('SITE_URL') ? SITE_URL : 'https://magos.bg', '/');
$posts = [];
try {
  $pdo = posts_db();
  $posts = $pdo->query("SELECT slug, published_at, updated_at FROM posts WHERE status='published' ORDER BY published_at DESC")->fetchAll();
} catch (Throwable $e) {
  $posts = [];
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= htmlspecialchars($base . '/statii/', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
<?php foreach ($posts as $post): ?>
  <url>
    <loc><?= htmlspecialchars($base . '/statii/' . rawurlencode((string)$post['slug']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></loc>
    <?php $lm = strtotime((string)($post['updated_at'] ?: $post['published_at'])); ?>
    <?php if ($lm): ?><lastmod><?= date('Y-m-d', $lm) ?></lastmod><?php endif; ?>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
<?php endforeach; ?>
</urlset>
