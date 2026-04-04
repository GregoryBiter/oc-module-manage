#!/usr/bin/env php
<?php
require_once __DIR__ . '/php_module/index.php';
// Пути
if (!defined('SCRIPT_DIR')) define('SCRIPT_DIR', dirname(__FILE__));
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
 * Запуск указанного скрипта из папки scripts.
 */
function run_script($script_name) {
    $scripts_dir = SCRIPT_DIR . '/scripts/';
    $path = $scripts_dir . $script_name;
    
    // Если файл не найден, пробуем найти с расширениями
    if (!file_exists($path)) {
        if (file_exists($path . '.php')) {
            $path .= '.php';
        } elseif (file_exists($path . '.sh')) {
            $path .= '.sh';
        }
    }

    if (file_exists($path) && is_file($path)) {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        echo "Запуск скрипта: " . basename($path) . "\n";
        
        if ($extension === 'php') {
            passthru("php " . escapeshellarg($path));
        } elseif ($extension === 'sh') {
            chmod($path, 0755);
            passthru("bash " . escapeshellarg($path));
        } else {
            chmod($path, 0755);
            passthru(escapeshellarg($path));
        }
    } else {
        return false;
    }
    return true;
}

// Инициализация приложения в стиле Artisan
$app = new \Ocm\Base\Application();

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

// Обработка опции --script (для обратной совместимости)
$script_option = null;
for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] === '--script' && isset($argv[$i+1])) {
        $script_option = $argv[$i+1];
        break;
    }
}

if ($script_option !== null) {
    run_script($script_option);
    exit;
}

// Запуск основного цикла приложения
$app->run($argv);