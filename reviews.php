<?php
declare(strict_types=1);

// reviews.php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $limit = (int)($_GET['limit'] ?? 12);
  if ($limit < 1 || $limit > 200) $limit = 12;

  // Ако нямаш company колона — махни company от SELECT
  $sql = "
    SELECT id, name, company, rating, message, created_at
    FROM reviews
    WHERE is_approved = 1
    ORDER BY created_at DESC, id DESC
    LIMIT :limit
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->execute();

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok' => true, 'reviews' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => 'Server error'], JSON_UNESCAPED_UNICODE);
}