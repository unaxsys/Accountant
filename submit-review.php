<?php
// submit-review.php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
  exit;
}

// Basic anti-spam honeypot (скрито поле)
$hp = trim((string)($_POST['website'] ?? ''));
if ($hp !== '') {
  http_response_code(200);
  echo json_encode(['ok' => true, 'message' => 'OK']);
  exit;
}

$name = trim((string)($_POST['name'] ?? ''));
$company = trim((string)($_POST['company'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$rating = (int)($_POST['rating'] ?? 0);
$message = trim((string)($_POST['message'] ?? ''));

if ($name === '' || $email === '' || $message === '' || $rating < 1 || $rating > 5) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Моля, попълнете всички задължителни полета и изберете оценка 1–5.']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Моля, въведете валиден имейл.']);
  exit;
}

try {
  $pdo = db();
  $stmt = $pdo->prepare("
    INSERT INTO reviews (name, company, email, rating, message, status, created_at, ip, user_agent)
    VALUES (:name, :company, :email, :rating, :message, 'pending', :created_at, :ip, :ua)
  ");
  $stmt->execute([
    ':name' => $name,
    ':company' => $company ?: null,
    ':email' => $email,
    ':rating' => $rating,
    ':message' => $message,
    ':created_at' => gmdate('c'),
    ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
  ]);

  // Optional email notify (може да не работи навсякъде, но не пречи)
  if (defined('NOTIFY_EMAIL') && NOTIFY_EMAIL) {
    $siteName = defined('SITE_NAME') ? SITE_NAME : (defined('SITE_URL') ? SITE_URL : 'Сайт');
    $subject = 'Нов отзив (чака одобрение) - ' . $siteName;
    $body = "Име: $name\nФирма: " . ($company ?: '-') . "\nИмейл: $email\nОценка: $rating\n\nОтзив:\n$message\n";
    @mail(NOTIFY_EMAIL, $subject, $body);
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'message' => 'Не успяхме да запишем отзива в момента. Моля, опитайте отново след малко.'
  ]);
  exit;
}

echo json_encode([
  'ok' => true,
  'message' => 'Благодарим! Отзивът ви ще бъде публикуван след одобрение.'
]);
