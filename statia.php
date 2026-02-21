<?php
$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
  header('Location: /statii/', true, 302);
  exit;
}
header('Location: /statii/' . rawurlencode($slug), true, 301);
exit;
