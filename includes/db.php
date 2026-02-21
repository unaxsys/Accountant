<?php
require_once __DIR__ . '/config.php';

function mysql_config_ok(): bool {
  return MYSQL_DB !== '' && MYSQL_USER !== '';
}

function posts_pdo(bool $public = false): PDO {
  static $pdo = null;

  if ($pdo instanceof PDO) {
    return $pdo;
  }

  if (!mysql_config_ok()) {
    if ($public) {
      http_response_code(500);
      exit;
    }
    throw new RuntimeException('Липсва MySQL конфигурация (MYSQL_DB / MYSQL_USER). Попълни includes/config.php.');
  }

  try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', MYSQL_HOST, MYSQL_PORT, MYSQL_DB);
    $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
  } catch (Throwable $e) {
    if ($public) {
      http_response_code(500);
      exit;
    }
    throw new RuntimeException('Неуспешна връзка с MySQL: ' . $e->getMessage());
  }
}
