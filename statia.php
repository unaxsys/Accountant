<?php
$DATA_FILE = __DIR__ . '/articles.json';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function load_articles($file){
  if (!file_exists($file)) return [];
  $data = json_decode(file_get_contents($file), true);
  return is_array($data) ? $data : [];
}

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') { http_response_code(404); exit('Not Found'); }

$article = null;
foreach (load_articles($DATA_FILE) as $a) {
  if (!empty($a['is_published']) && ($a['slug'] ?? '') === $slug) { $article = $a; break; }
}
if (!$article) { http_response_code(404); exit('Not Found'); }
?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h($article['title'] ?? '') ?> | Магос ЕООД</title>
  <?php $desc = trim((string)($article['excerpt'] ?? '')); ?>
  <meta name="description" content="<?= h(function_exists('mb_substr') ? mb_substr($desc, 0, 155, 'UTF-8') : substr($desc, 0, 155)) ?>" />
  <link rel="stylesheet" href="/styles.css" />
</head>
<body>
  <main style="max-width:900px;margin:90px auto 40px;padding:0 16px;">
    <a class="btn btn-ghost" href="/statii.php">← Всички статии</a>
    <h1 style="margin-top:12px;"><?= h($article['title'] ?? '') ?></h1>
    <p class="tag"><?= h(date('d.m.Y', strtotime((string)($article['created_at'] ?? 'now')))) ?></p>

    <?php if (!empty($article['excerpt'])): ?>
      <p><em><?= nl2br(h($article['excerpt'])) ?></em></p>
    <?php endif; ?>

    <div class="card" style="margin-top:16px;">
      <?= nl2br(h($article['content'] ?? '')) ?>
    </div>
  </main>
</body>
</html>
