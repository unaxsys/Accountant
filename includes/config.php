<?php
// includes/config.php

if (!defined('MYSQL_HOST')) define('MYSQL_HOST', 'localhost');
if (!defined('MYSQL_PORT')) define('MYSQL_PORT', '3306');

if (!defined('MYSQL_DB'))   define('MYSQL_DB', 'magosbgf_blog');
if (!defined('MYSQL_USER')) define('MYSQL_USER', 'magosbgf_bloguser');
if (!defined('MYSQL_PASS')) define('MYSQL_PASS', 'Lamer4e*#');

if (!defined('SITE_URL'))   define('SITE_URL', 'https://magos.bg');
// ===========================
// ADMIN LOGIN CONFIG
// ===========================
if (!defined('ADMIN_USER')) {
  define('ADMIN_USER', 'admin');
}

if (!defined('ADMIN_PASS_HASH')) {
  // TEMP placeholder – ще го сменим след 2 минути с валиден hash
  define('$2y$12$PIfrSLFIjTlPoB.iHmflSePdSQRHP1WQdaYJUcbg./SJFSb3i/wOS, '');
}