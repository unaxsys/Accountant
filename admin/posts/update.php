<?php
require_once __DIR__ . '/_common.php';

posts_require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  posts_json(['ok' => false, 'error' => 'Method Not Allowed'], 405);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  posts_json(['ok' => false, 'error' => 'Missing id'], 422);
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

  $existing = $pdo->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
  $existing->execute([':id' => $id]);
  $post = $existing->fetch();
  if (!$post) {
    posts_json(['ok' => false, 'error' => 'Post not found'], 404);
  }

  $slug = unique_post_slug($pdo, slugify_post($slugInput !== '' ? $slugInput : $title), $id);
  $cover = posts_upload_cover('cover_image');
  if ($cover === null) {
    $cover = $post['cover_image'];
  }

  $publishedAt = $post['published_at'];
  if ($status === 'published' && empty($publishedAt)) {
    $publishedAt = now_sql();
  }
  if ($status === 'draft') {
    $publishedAt = null;
  }

  $stmt = $pdo->prepare('UPDATE posts SET slug=:slug,title=:title,meta_description=:meta,excerpt=:excerpt,content_html=:content,cover_image=:cover,tags=:tags,status=:status,published_at=:published_at,updated_at=:updated_at WHERE id=:id');
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
    ':updated_at' => now_sql(),
    ':id' => $id,
  ]);

  posts_json(['ok' => true, 'id' => $id, 'slug' => $slug]);
} catch (Throwable $e) {
  posts_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
