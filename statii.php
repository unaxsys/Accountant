<?php
$DATA_FILE = __DIR__ . '/articles.json';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function load_articles($file){
  if (!file_exists($file)) return [];
  $data = json_decode(file_get_contents($file), true);
  return is_array($data) ? $data : [];
}

$items = array_values(array_filter(load_articles($DATA_FILE), fn($a) => !empty($a['is_published'])));
usort($items, fn($a,$b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Статии | Магос ЕООД</title>
  <meta name="description" content="Полезни статии за счетоводство, ДДС и ТРЗ от Магос ЕООД." />
  <link rel="stylesheet" href="/styles.css" />
</head>
<body>
  <main style="max-width:1100px;margin:90px auto 40px;padding:0 16px;">
    <h1>Статии</h1>

    <div class="cards">
      <?php if (!$items): ?>
        <article class="card"><h3>Няма публикувани статии</h3><p>Скоро ще добавим първите материали.</p></article>
      <?php endif; ?>

      <?php foreach ($items as $a): ?>
        <article class="card">
          <p class="tag"><?= h(date('d.m.Y', strtotime((string)($a['created_at'] ?? 'now')))) ?></p>
          <h3><?= h($a['title'] ?? '') ?></h3>
          <p><?= h(($a['excerpt'] ?? '') ?: 'Прочетете статията за повече подробности.') ?></p>
          <a class="btn btn-ghost" href="/statia.php?slug=<?= h($a['slug'] ?? '') ?>">Прочети →</a>
        </article>
      <?php endforeach; ?>
    </div>
  </main>
</body>
</html>
