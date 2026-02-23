<?php

// reviews.php
require_once __DIR__ . '/db.php';

$pdo = db();

$limit = (int)($_GET['limit'] ?? 50);
if ($limit < 1 || $limit > 200) $limit = 50;

$stmt = $pdo->prepare("
  SELECT id, name, company, rating, message, created_at
  FROM reviews
  WHERE status = 'approved'
  ORDER BY datetime(approved_at) DESC, datetime(created_at) DESC
  LIMIT :limit
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'reviews' => $rows], JSON_UNESCAPED_UNICODE);

