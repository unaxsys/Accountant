<?php

if (!function_exists('findSimilarContent')) {
  function findSimilarContent(PDO $pdo, string $content, int $threshold = 60) {
    $normalize = static function (string $value): string {
      $slice = function_exists('mb_substr') ? mb_substr($value, 0, 5000, 'UTF-8') : substr($value, 0, 5000);
      $stripped = strip_tags($slice);
      return function_exists('mb_strtolower') ? mb_strtolower($stripped, 'UTF-8') : strtolower($stripped);
    };

    $normalizedContent = $normalize($content);
    if ($normalizedContent === '') {
      return null;
    }

    $stmt = $pdo->query('SELECT id, title, slug, content_html FROM posts');
    if (!$stmt) {
      return null;
    }

    $mostSimilar = null;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $existingContent = $normalize((string)($row['content_html'] ?? ''));
      if ($existingContent === '') {
        continue;
      }

      similar_text($normalizedContent, $existingContent, $similarity);
      if ($similarity < $threshold) {
        continue;
      }

      if ($mostSimilar === null || $similarity > $mostSimilar['similarity']) {
        $mostSimilar = [
          'id' => (int)($row['id'] ?? 0),
          'title' => (string)($row['title'] ?? ''),
          'slug' => (string)($row['slug'] ?? ''),
          'similarity' => round($similarity, 2),
        ];
      }
    }

    return $mostSimilar;
  }
}
