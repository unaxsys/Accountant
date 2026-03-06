<?php
require_once __DIR__ . '/../../posts_lib.php';

function ai_json(array $payload, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

function ai_read_secret(): string {
  if (defined('AI_PUBLISH_SECRET') && is_string(AI_PUBLISH_SECRET) && AI_PUBLISH_SECRET !== '') {
    return AI_PUBLISH_SECRET;
  }

  $env = getenv('AI_PUBLISH_SECRET');
  if (is_string($env) && $env !== '') {
    return $env;
  }

  return '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  ai_json(['ok' => false, 'error' => 'Method Not Allowed'], 405);
}

$secret = ai_read_secret();
if ($secret === '') {
  ai_json(['ok' => false, 'error' => 'AI publishing secret is not configured'], 500);
}

$providedSecret = (string)($_SERVER['HTTP_X_AI_SECRET'] ?? '');
if ($providedSecret === '') {
  $providedSecret = (string)($_GET['secret'] ?? '');
}

if (!hash_equals($secret, $providedSecret)) {
  ai_json(['ok' => false, 'error' => 'Forbidden'], 403);
}

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
  ai_json(['ok' => false, 'error' => 'Invalid JSON payload'], 422);
}

$title = trim((string)($data['title'] ?? ''));
$slugInput = trim((string)($data['slug'] ?? ''));
$category = trim((string)($data['category'] ?? ''));
$meta = trim((string)($data['meta'] ?? ''));
$excerpt = trim((string)($data['excerpt'] ?? ''));
$tags = trim((string)($data['tags'] ?? ''));
$content = trim((string)($data['content'] ?? ''));
$cover = trim((string)($data['cover'] ?? ''));

if ($title === '' || $content === '') {
  ai_json(['ok' => false, 'error' => 'title and content are required'], 422);
}

if ($cover !== '' && strpos($cover, '/uploads/') !== 0) {
  ai_json(['ok' => false, 'error' => 'cover must point to /uploads/*'], 422);
}

try {
  $pdo = posts_db();

  $baseSlug = slugify_post($slugInput !== '' ? $slugInput : $title);
  $slug = unique_post_slug($pdo, $baseSlug, null);
  $now = now_sql();

  $stmt = $pdo->prepare(
    'INSERT INTO posts (title, slug, category, meta_description, excerpt, content_html, cover_image, tags, status, published_at, created_at, updated_at)
     VALUES (:title, :slug, :category, :meta_description, :excerpt, :content_html, :cover_image, :tags, :status, :published_at, :created_at, :updated_at)'
  );

  $stmt->execute([
    ':title' => $title,
    ':slug' => $slug,
    ':category' => $category !== '' ? $category : null,
    ':meta_description' => utf8_cut($meta, 160),
    ':excerpt' => $excerpt,
    ':content_html' => $content,
    ':cover_image' => $cover !== '' ? $cover : null,
    ':tags' => $tags !== '' ? $tags : null,
    ':status' => 'published',
    ':published_at' => $now,
    ':created_at' => $now,
    ':updated_at' => $now,
  ]);

  ai_json([
    'ok' => true,
    'id' => (int)$pdo->lastInsertId(),
    'slug' => $slug,
  ], 201);
} catch (Throwable $e) {
  ai_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
