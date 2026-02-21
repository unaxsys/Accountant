<?php
require_once __DIR__ . '/_common.php';
posts_require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') posts_json(['ok'=>false,'error'=>'Method Not Allowed'],405);
$id=(int)($_GET['id'] ?? 0);
if ($id<=0) posts_json(['ok'=>false,'error'=>'Missing id'],422);
try {
  $pdo=posts_db();
  $stmt=$pdo->prepare("UPDATE posts SET status='draft', updated_at=:now WHERE id=:id");
  $stmt->execute([':id'=>$id,':now'=>now_sql()]);
  posts_json(['ok'=>true]);
} catch (Throwable $e) {
  posts_json(['ok'=>false,'error'=>$e->getMessage()],500);
}
