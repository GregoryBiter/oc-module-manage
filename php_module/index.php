<?php

// Пути
if (!defined('SCRIPT_DIR')) define('SCRIPT_DIR', dirname(dirname(__FILE__)));

// Автозагрузка через Composer
$autoloadFile = dirname(dirname(__FILE__)) . '/vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    die("Error: composer autoloader not found. Run 'composer install' in the root directory.\n");
}

require_once $autoloadFile;
