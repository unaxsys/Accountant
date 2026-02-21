<?php
require_once __DIR__ . '/../../posts_lib.php';

session_start();

function posts_require_admin(): void {
  if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

function posts_json(array $payload, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

function posts_upload_cover(string $field = 'cover_image'): ?string {
  if (empty($_FILES[$field]['name'])) {
    return null;
  }

  if (!is_uploaded_file($_FILES[$field]['tmp_name'])) {
    return null;
  }

  $ext = strtolower(pathinfo((string)$_FILES[$field]['name'], PATHINFO_EXTENSION));
  $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
  if (!in_array($ext, $allowed, true)) {
    throw new RuntimeException('Невалиден формат на изображение.');
  }

  $dir = __DIR__ . '/../../uploads/posts';
  if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
  }

  $name = date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
  $target = $dir . '/' . $name;
  if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
    throw new RuntimeException('Качването на изображението беше неуспешно.');
  }

  return '/uploads/posts/' . $name;
}
