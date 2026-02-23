<?php

declare(strict_types=1);

if (is_file(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

function base_url(): string {
    $base = getenv('BASE_URL');
    if ($base && trim($base) !== '') {
        return rtrim($base, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: (defined('MYSQL_HOST') ? MYSQL_HOST : '127.0.0.1');
    $port = getenv('DB_PORT') ?: (defined('MYSQL_PORT') ? MYSQL_PORT : '3306');
    $name = getenv('DB_NAME') ?: (defined('MYSQL_DB') ? MYSQL_DB : '');
    $user = getenv('DB_USER') ?: (defined('MYSQL_USER') ? MYSQL_USER : '');
    $pass = getenv('DB_PASS') ?: (defined('MYSQL_PASS') ? MYSQL_PASS : '');

    if ($name === '' || $user === '') {
        throw new RuntimeException('Липсва MySQL конфигурация (DB_NAME / DB_USER или MYSQL_DB / MYSQL_USER).');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slugify_bg(string $text): string {
    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ж'=>'j','з'=>'z','и'=>'i','й'=>'y',
        'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
        'ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sht','ъ'=>'a','ь'=>'','ю'=>'yu','я'=>'ya'
    ];

    $t = mb_strtolower(trim($text), 'UTF-8');
    $out = '';
    $len = mb_strlen($t, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $ch = mb_substr($t, $i, 1, 'UTF-8');
        $out .= $map[$ch] ?? $ch;
    }

    $out = preg_replace('~[^a-z0-9]+~', '-', $out);
    $out = trim((string)$out, '-');
    return $out ?: 'post';
}

function extract_faq_from_html(string $html): array {
    $html = (string)$html;
    if ($html === '') {
        return [];
    }

    if (mb_stripos($html, 'Често задавани въпроси', 0, 'UTF-8') === false && mb_stripos($html, 'FAQ', 0, 'UTF-8') === false) {
        return [];
    }

    $faqs = [];
    $pattern = '~<h3[^>]*>(.*?)</h3>\s*(?:<p[^>]*>(.*?)</p>|<div[^>]*>(.*?)</div>)~si';
    if (preg_match_all($pattern, $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) {
            $q = trim(strip_tags($row[1] ?? ''));
            $aRaw = $row[2] ?: ($row[3] ?? '');
            $a = trim(strip_tags($aRaw));
            if ($q !== '' && $a !== '') {
                $faqs[] = ['q' => $q, 'a' => $a];
            }
            if (count($faqs) >= 10) {
                break;
            }
        }
    }

    return $faqs;
}

function breadcrumbs_for_post(array $post): array {
    $base = base_url();
    return [
        ['name' => 'Начало', 'url' => $base . '/'],
        ['name' => 'Блог', 'url' => $base . '/blog/'],
        ['name' => $post['title'] ?? 'Статия', 'url' => $base . '/blog/' . ($post['slug'] ?? '')],
    ];
}

function jsonld_breadcrumbs(array $crumbs): string {
    $itemList = [];
    $pos = 1;
    foreach ($crumbs as $c) {
        $itemList[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'name' => $c['name'],
            'item' => $c['url'],
        ];
    }

    return json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $itemList,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}
