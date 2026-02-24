<?php
// public_html/db.php
require_once __DIR__ . '/includes/db.php';

// Backward compatible wrapper: старият код вика db()
function db(): PDO {
  return posts_pdo(false);
}