<?php
declare(strict_types=1);

const BLOG_SITEMAP_DATA_FILE = __DIR__ . '/data/posts.json';
const BLOG_SITEMAP_BASE_URL = 'https://magos.bg';

header('Content-Type: application/xml; charset=UTF-8');

$posts = [];
if (is_file(BLOG_SITEMAP_DATA_FILE)) {
    $raw = file_get_contents(BLOG_SITEMAP_DATA_FILE);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded)) {
        $posts = array_values(array_filter($decoded, static fn($post) => is_array($post) && !empty($post['slug'])));
    }
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= htmlspecialchars(BLOG_SITEMAP_BASE_URL . '/blog/', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
<?php foreach ($posts as $post): ?>
  <url>
    <loc><?= htmlspecialchars(BLOG_SITEMAP_BASE_URL . '/blog/' . rawurlencode((string)$post['slug']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></loc>
    <?php if (!empty($post['date'])): ?><lastmod><?= date('Y-m-d', strtotime((string)$post['date']) ?: time()) ?></lastmod><?php endif; ?>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
<?php endforeach; ?>
</urlset>
