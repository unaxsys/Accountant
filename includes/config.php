<?php
// includes/config.php
// Попълни стойностите по-долу за MySQL (или остави env променливите да ги подадат).

if (!defined('MYSQL_HOST')) define('MYSQL_HOST', getenv('MYSQL_HOST') ?: 'localhost');
if (!defined('MYSQL_PORT')) define('MYSQL_PORT', getenv('MYSQL_PORT') ?: '3306');
if (!defined('MYSQL_DB')) define('MYSQL_DB', getenv('MYSQL_DB') ?: '');
if (!defined('MYSQL_USER')) define('MYSQL_USER', getenv('MYSQL_USER') ?: '');
if (!defined('MYSQL_PASS')) define('MYSQL_PASS', getenv('MYSQL_PASS') ?: '');
if (!defined('SITE_URL')) define('SITE_URL', getenv('SITE_URL') ?: 'https://magos.bg');
