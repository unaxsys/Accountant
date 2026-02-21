<?php
// db.php
require_once __DIR__ . '/config.php';

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Create table if not exists
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS reviews (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      company TEXT,
      email TEXT,
      rating INTEGER NOT NULL,
      message TEXT NOT NULL,
      status TEXT NOT NULL DEFAULT 'pending',
      created_at TEXT NOT NULL,
      approved_at TEXT,
      ip TEXT,
      user_agent TEXT
    );
  ");

  return $pdo;
}
