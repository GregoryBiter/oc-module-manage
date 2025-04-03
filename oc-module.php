#!/usr/bin/env php
<?php
require_once __DIR__ . '/php_module/index.php';
// Пути
define('SCRIPT_DIR', dirname(__FILE__));
define('CURRENT_DIR', getcwd());
define('MODULE_DIR', CURRENT_DIR . '/upload');
define('OPENCART_DIR', dirname(dirname(CURRENT_DIR)));
define('JSON_FILE', CURRENT_DIR . '/opencart-module.json');
define('TEMPLATES_DIR', SCRIPT_DIR . '/templates');
define('BUILD_FILE', CURRENT_DIR . '/.build-module');
define('BUILD_DIR', CURRENT_DIR . '/build-module/upload');

// Хранение времени последней модификации файлов
$last_modified_times = [];


/**
 * Запуск указанного скрипта.
 */
function run_script($script_path) {
    $path = __DIR__ . '/scripts/' . $script_path;
    if (file_exists($path) && is_file($path)) {

        include($path);
    } else {
        echo "Скрипт {$path} не найден.\n";
    }
}

// Определяем основную команду из аргументов
$command = isset($argv[1]) ? $argv[1] : 'help';

// Проверяем, есть ли флаг -a в аргументах
$archive_flag = false;
foreach ($argv as $arg) {
    if ($arg === '-a') {
        $archive_flag = true;
        break;
    }
}

// Обработка опции --script
$script_option = null;
for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] === '--script' && isset($argv[$i+1])) {
        $script_option = $argv[$i+1];
        break;
    }
}

// Фильтруем аргументы, оставляя только команды и их параметры
$command_args = [];
foreach ($argv as $i => $arg) {
    // Пропускаем имя скрипта и основную команду
    if ($i <= 1) continue;
    // Пропускаем опции с --
    if (strpos($arg, '--') === 0) continue;
    // Пропускаем аргумент после --script
    if ($arg === '--script') {
        $i++; // Пропускаем следующий аргумент
        continue;
    }
    // Пропускаем опции с -
    if (strpos($arg, '-') === 0 && strlen($arg) == 2) continue;
    
    $command_args[] = $arg;
}


if ($script_option !== null) {
    run_script($script_option);
    exit;
}

switch ($command) {
    case 'init':
        init($command_args);
        break;
    case 'install':
        install($command_args);
        break;
    case 'dev':
        dev($command_args);
        break;
    case 'remove':
        remove($command_args);
        break;
    case 'create':
        create($command_args);
        break;
    case 'build':
        build($command_args, $archive_flag);
        break;
    case 'return': // Добавляем новую команду
        return_files($command_args);
        break;
    case 'help':
    default:
        show_help();
        break;
}

if ($script_option !== null) {
    run_script($script_option);
}
