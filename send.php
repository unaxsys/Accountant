<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Невалиден метод на заявка.']);
    exit;
}

$honeypot = trim((string)($_POST['website'] ?? ''));
if ($honeypot !== '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Заявката е отхвърлена.']);
    exit;
}

$name = trim((string)($_POST['name'] ?? ''));
$company = trim((string)($_POST['company'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$service = trim((string)($_POST['service'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if (
    $name === '' ||
    $company === '' ||
    $email === '' ||
    $phone === '' ||
    $service === ''
) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Моля, попълнете всички задължителни полета.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Моля, въведете валиден имейл адрес.']);
    exit;
}

if (mb_strlen($name) > 120 || mb_strlen($company) > 120 || mb_strlen($phone) > 50 || mb_strlen($service) > 120 || mb_strlen($message) > 4000) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Въведените данни са невалидни.']);
    exit;
}

$subject = 'Ново запитване от формата на magos.bg';
$body = "Име и фамилия: {$name}\n" .
    "Фирма: {$company}\n" .
    "Имейл: {$email}\n" .
    "Телефон: {$phone}\n" .
    "Услуга: {$service}\n\n" .
    "Описание:\n" .
    ($message !== '' ? $message : 'Няма добавено описание.');

$to = 'office@magos.bg';
$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: no-reply@magos.bg',
    "Reply-To: {$email}",
    'X-Mailer: PHP/' . phpversion(),
];

$sent = mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));

if (!$sent) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Възникна проблем при изпращането. Моля, опитайте отново.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Благодарим! Запитването е изпратено успешно.']);
