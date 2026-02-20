<?php
// send.php
header('Content-Type: application/json; charset=utf-8');

// Само POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
  exit;
}

// Anti-spam honeypot (ако полето е попълнено -> бот)
$hp = trim($_POST['website'] ?? '');
if ($hp !== '') {
  echo json_encode(['ok' => true, 'message' => 'OK']);
  exit;
}

// Вземи и валидирай полетата
$name    = trim($_POST['name'] ?? '');
$company = trim($_POST['company'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$service = trim($_POST['service'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $company === '' || $email === '' || $phone === '' || $service === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Моля, попълнете всички задължителни полета.']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Невалиден имейл адрес.']);
  exit;
}

// Къде да идва (получател)
$to = 'office@magos.bg';

// Тема
$subject = "Ново запитване от сайта: {$name} ({$company})";

// Тяло
$body = "Име: {$name}\n";
$body .= "Фирма: {$company}\n";
$body .= "Имейл: {$email}\n";
$body .= "Телефон: {$phone}\n";
$body .= "Услуга: {$service}\n";
$body .= "-------------------------\n";
$body .= "Съобщение:\n{$message}\n";

// Важно: From да е от домейна ти, иначе често влиза в SPAM/се блокира
// Замени magos.bg с твоя домейн, ако е друг.
$fromEmail = 'no-reply@magos.bg';
$fromName  = 'Magos.bg';

// Headers
$headers = [];
$headers[] = "MIME-Version: 1.0";
$headers[] = "Content-Type: text/plain; charset=UTF-8";
$headers[] = "From: {$fromName} <{$fromEmail}>";
$headers[] = "Reply-To: {$name} <{$email}>";
$headers[] = "X-Mailer: PHP/" . phpversion();

$headersStr = implode("\r\n", $headers);

// Изпрати
$ok = mail($to, $subject, $body, $headersStr);

if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => 'Грешка при изпращане. Опитайте пак.']);
  exit;
}

echo json_encode(['ok' => true, 'message' => 'Запитването е изпратено успешно!']);
