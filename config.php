<?php
// config.php

// ✅ СМЕНИ потребителя
define('ADMIN_USER', 'admin');

// ✅ СМЕНИ паролата: по подразбиране е "ChangeMe_123!"
// Ако искаш нова парола, кажи ми и ще ти дам нов hash.
define('ADMIN_PASS_HASH', '$2y$10$qQ3WtRKA4HIHsNM/m7RCOOPzeKxxedSkxe9a9AktWUidyJbl.ddv.');

// SQLite файл (ще се създаде автоматично)
define('DB_PATH', __DIR__ . '/db.sqlite');

// Имейл за нотификация при нов отзив (по желание)
define('NOTIFY_EMAIL', 'office@magos.bg');

// Базов сайт URL (по желание)
define('SITE_NAME', 'Магос ЕООД');

require_once __DIR__ . '/includes/config.php';
