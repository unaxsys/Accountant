<?php
require_once __DIR__ . '/_common.php';

posts_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  posts_json(['ok' => false, 'error' => 'Method Not Allowed'], 405);
}

try {
  $pdo = posts_db();
  $title = trim((string)($_POST['title'] ?? ''));
  $excerpt = trim((string)($_POST['excerpt'] ?? ''));
  $meta = trim((string)($_POST['meta_description'] ?? ''));
  $content = trim((string)($_POST['content_html'] ?? ''));
  $tags = trim((string)($_POST['tags'] ?? ''));
  $status = (string)($_POST['status'] ?? 'draft');
  $slugInput = trim((string)($_POST['slug'] ?? ''));

  if ($title === '' || $content === '') {
    posts_json(['ok' => false, 'error' => 'Заглавие и съдържание са задължителни.'], 422);
  }

  if (!in_array($status, ['draft', 'published'], true)) {
    $status = 'draft';
  }

  $baseSlug = slugify_post($slugInput !== '' ? $slugInput : $title);
  $slug = unique_post_slug($pdo, $baseSlug, null);
  $cover = posts_upload_cover('cover_image');

  $now = now_sql();
  $publishedAt = $status === 'published' ? $now : null;

  $stmt = $pdo->prepare('INSERT INTO posts (slug,title,meta_description,excerpt,content_html,cover_image,tags,status,published_at,created_at,updated_at)
    VALUES (:slug,:title,:meta,:excerpt,:content,:cover,:tags,:status,:published_at,:created_at,:updated_at)');
  $stmt->execute([
    ':slug' => $slug,
    ':title' => $title,
    ':meta' => utf8_cut($meta, 160),
    ':excerpt' => $excerpt,
    ':content' => $content,
    ':cover' => $cover,
    ':tags' => $tags !== '' ? $tags : null,
    ':status' => $status,
    ':published_at' => $publishedAt,
    ':created_at' => $now,
    ':updated_at' => $now,
  ]);

  posts_json(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'slug' => $slug]);
} catch (Throwable $e) {
  posts_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
