#!/usr/bin/env php
<?php

require_once __DIR__ . '/php_module/index.php';

// Инициализация приложения
$app = new \Ocm\Base\Application();

// Определение путей и констант
if (!defined('SCRIPT_DIR')) define('SCRIPT_DIR', dirname(__FILE__));
define('CURRENT_DIR', getcwd());
define('MODULE_DIR', CURRENT_DIR . '/upload');

$config = $app->getService('config');
$opencart_paths = $config->findOpenCartPaths();

if (!empty($opencart_paths)) {
    define('OPENCART_PATHS', $opencart_paths);
    define('OPENCART_DIR', $opencart_paths[0]);
} else {
    // Дефолтный путь если не найдено
    $defaultPath = dirname(dirname(CURRENT_DIR));
    define('OPENCART_PATHS', [$defaultPath]);
    define('OPENCART_DIR', $defaultPath);
}

define('JSON_FILE', CURRENT_DIR . '/opencart-module.json');
define('FILES_JSON', CURRENT_DIR . '/.ocm_files.json');
define('TEMPLATES_DIR', SCRIPT_DIR . '/templates');
define('BUILD_FILE', CURRENT_DIR . '/.build-module');
define('BUILD_DIR', CURRENT_DIR . '/build-module/upload');

// Регистрация команд
$app->add(new \Ocm\Commands\InitCommand());
$app->add(new \Ocm\Commands\InstallCommand());
$app->add(new \Ocm\Commands\DevCommand());
$app->add(new \Ocm\Commands\RemoveCommand());
$app->add(new \Ocm\Commands\ReturnCommand());
$app->add(new \Ocm\Commands\CreateCommand());
$app->add(new \Ocm\Commands\BuildCommand());
$app->add(new \Ocm\Commands\MigrateCommand());
$app->add(new \Ocm\Commands\HelpCommand());

// Обработка опции --script (legacy)
$script_option = null;
for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] === '--script' && isset($argv[$i+1])) {
        $script_option = $argv[$i+1];
        break;
    }
}

if ($script_option !== null) {
    // Временная реализация run_script для совместимости
    $scripts_dir = SCRIPT_DIR . '/scripts/';
    $path = $scripts_dir . $script_option;
    if (!file_exists($path)) {
        if (file_exists($path . '.php')) $path .= '.php';
        elseif (file_exists($path . '.sh')) $path .= '.sh';
    }

    if (file_exists($path) && is_file($path)) {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($extension === 'php') passthru("php " . escapeshellarg($path));
        elseif ($extension === 'sh') passthru("bash " . escapeshellarg($path));
        else passthru(escapeshellarg($path));
    }
    exit;
}

// Запуск основного цикла приложения
$app->run($argv);