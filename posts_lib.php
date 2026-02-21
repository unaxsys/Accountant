<?php
require_once __DIR__ . '/config.php';

function posts_db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) {
    return $pdo;
  }

  $host = defined('MYSQL_HOST') ? MYSQL_HOST : (getenv('MYSQL_HOST') ?: '127.0.0.1');
  $port = defined('MYSQL_PORT') ? MYSQL_PORT : (getenv('MYSQL_PORT') ?: '3306');
  $name = defined('MYSQL_DB') ? MYSQL_DB : (getenv('MYSQL_DB') ?: '');
  $user = defined('MYSQL_USER') ? MYSQL_USER : (getenv('MYSQL_USER') ?: '');
  $pass = defined('MYSQL_PASS') ? MYSQL_PASS : (getenv('MYSQL_PASS') ?: '');

  if ($name === '' || $user === '') {
    throw new RuntimeException('Липсва MySQL конфигурация (MYSQL_DB / MYSQL_USER).');
  }

  $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  return $pdo;
}

function slugify_post(string $text): string {
  $text = trim(mb_strtolower($text, 'UTF-8'));
  $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sht','ъ'=>'a','ь'=>'','ю'=>'yu','я'=>'ya'];
  $text = strtr($text, $map);
  $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
  $text = trim((string)$text, '-');
  return $text !== '' ? $text : 'statia';
}

function unique_post_slug(PDO $pdo, string $base, ?int $ignoreId = null): string {
  $slug = $base;
  $i = 1;
  while (true) {
    $sql = 'SELECT id FROM posts WHERE slug = :slug';
    $params = [':slug' => $slug];
    if ($ignoreId !== null) {
      $sql .= ' AND id != :id';
      $params[':id'] = $ignoreId;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    if (!$stmt->fetch()) {
      return $slug;
    }
    $slug = $base . '-' . $i;
    $i++;
  }
}

function now_sql(): string {
  return date('Y-m-d H:i:s');
}

function utf8_cut(string $text, int $max): string {
  if ($max <= 0) return '';
  if (function_exists('mb_substr')) return mb_substr($text, 0, $max, 'UTF-8');
  return substr($text, 0, $max);
}
