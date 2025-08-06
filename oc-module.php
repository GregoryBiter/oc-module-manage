#!/usr/bin/env php
<?php
require_once __DIR__ . '/php_module/index.php';
// Пути
define('SCRIPT_DIR', dirname(__FILE__));
define('CURRENT_DIR', getcwd());
define('MODULE_DIR', CURRENT_DIR . '/upload');

// Определение пути к OpenCart
function find_opencart_path() {
    // Проверяем наличие файла .path-opencart
    $custom_path = getcwd() . '/.path-opencart';
    if (file_exists($custom_path)) {
        $path_content = trim(file_get_contents($custom_path));
        if (!empty($path_content)) {
            echo "Используется путь из файла .path-opencart: {$path_content}\n";
            return $path_content;
        }
    }

    // Если .path-opencart не найден или пуст, ищем через config.php и admin/config.php
    $current_dir = getcwd();
    while ($current_dir !== '/') {
        $config_file = $current_dir . '/config.php';
        $admin_config_file = $current_dir . '/admin/config.php';

        if (file_exists($config_file) && file_exists($admin_config_file)) {
            return $current_dir; // Возвращаем путь к OpenCart
        }

        // Переходим в родительскую директорию
        $current_dir = dirname($current_dir);
    }

    return null; // Если путь не найден
}

// Используем функцию для определения пути
$opencart_path = find_opencart_path();
if ($opencart_path) {
    define('OPENCART_DIR', $opencart_path);
} else {
    echo "Ошибка: Путь к OpenCart не найден.\n";
    define('OPENCART_DIR', dirname(dirname(CURRENT_DIR))); // Используем путь по умолчанию
}

define('JSON_FILE', CURRENT_DIR . '/opencart-module.json');
define('FILES_JSON', CURRENT_DIR . '/.ocm_files.json');
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
    case 'migrate': // Команда миграции данных
        migrate($command_args);
        break;
    case 'help':
    default:
        show_help();
        break;
}