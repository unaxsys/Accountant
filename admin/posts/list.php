<?php
require_once __DIR__ . '/_common.php';
posts_require_admin();
try {
  $pdo = posts_db();
  $rows = $pdo->query('SELECT * FROM posts ORDER BY created_at DESC')->fetchAll();
  posts_json(['ok' => true, 'items' => $rows]);
} catch (Throwable $e) {
  posts_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
