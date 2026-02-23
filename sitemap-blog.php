<?php
require_once __DIR__ . '/includes/blog_helpers.php';

$pdo = db();
$base = base_url();

header('Content-Type: application/xml; charset=utf-8');

$cacheDir = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/sitemap-blog.xml';
$cacheTtl = 1800;

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}

if (file_exists($cacheFile) && (time() - (int)filemtime($cacheFile) < $cacheTtl)) {
    readfile($cacheFile);
    exit;
}

$stmt = $pdo->prepare("
  SELECT slug, updated_at, published_at
  FROM posts
  WHERE status='published'
  ORDER BY published_at DESC, updated_at DESC
");
$stmt->execute();
$rows = $stmt->fetchAll();

$xml = [];
$xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
$xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

$xml[] = '<url>';
$xml[] = '<loc>' . h($base . '/blog/') . '</loc>';
$xml[] = '<changefreq>daily</changefreq>';
$xml[] = '<priority>0.8</priority>';
$xml[] = '</url>';

foreach ($rows as $r) {
    $loc = $base . '/blog/' . $r['slug'];
    $lastmod = $r['updated_at'] ?: $r['published_at'];
    $lastmodIso = $lastmod ? date('c', strtotime((string)$lastmod)) : date('c');

    $xml[] = '<url>';
    $xml[] = '<loc>' . h($loc) . '</loc>';
    $xml[] = '<lastmod>' . h($lastmodIso) . '</lastmod>';
    $xml[] = '<changefreq>monthly</changefreq>';
    $xml[] = '<priority>0.6</priority>';
    $xml[] = '</url>';
}

$xml[] = '</urlset>';

$out = implode("\n", $xml);
file_put_contents($cacheFile, $out);
echo $out;
