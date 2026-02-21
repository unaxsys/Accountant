<?php
// config.php

// ✅ СМЕНИ потребителя
define('ADMIN_USER', 'admin');

// ✅ СМЕНИ паролата: по подразбиране е "ChangeMe_123!"
// Ако искаш нова парола, кажи ми и ще ти дам нов hash.
define('ADMIN_PASS_HASH', '$2y$10$uArtofqvwQ27z.iSyVEJ.O8LPgFeeNofyyPuLI/9MFC0rYuI/4Qx2');

// SQLite файл (ще се създаде автоматично)
define('DB_PATH', __DIR__ . '/db.sqlite');

// Имейл за нотификация при нов отзив (по желание)
define('NOTIFY_EMAIL', 'office@magos.bg');

// Базов сайт URL (по желание)
define('SITE_NAME', 'Магос ЕООД');
//