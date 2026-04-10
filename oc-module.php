#!/usr/bin/env php
<?php
require_once __DIR__ . '/php_module/index.php';
// Пути
if (!defined('SCRIPT_DIR')) define('SCRIPT_DIR', dirname(__FILE__));
define('CURRENT_DIR', getcwd());
define('MODULE_DIR', CURRENT_DIR . '/upload');

// Определение путей к OpenCart
function find_opencart_paths() {
    $paths = [];
    
    // Проверяем наличие файла .opencart (новый формат, может быть несколько путей)
    $opencart_file = getcwd() . '/.opencart';
    if (file_exists($opencart_file)) {
        $lines = file($opencart_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $path = trim($line);
            if (!empty($path)) {
                if (is_dir($path)) {
                    $paths[] = realpath($path);
                } else {
                    echo "Предупреждение: Путь из .opencart не найден: {$path}\n";
                }
            }
        }
    }

    // Если .opencart не найден, проверяем наличие файла .path-opencart (старый формат)
    if (empty($paths)) {
        $custom_path = getcwd() . '/.path-opencart';
        if (file_exists($custom_path)) {
            $path_content = trim(file_get_contents($custom_path));
            if (!empty($path_content) && is_dir($path_content)) {
                $paths[] = realpath($path_content);
            }
        }
    }

    // Если пути не найдены, ищем через config.php и admin/config.php вверх по дереву
    if (empty($paths)) {
        $current_dir = getcwd();
        while ($current_dir !== '/') {
            $config_file = $current_dir . '/config.php';
            $admin_config_file = $current_dir . '/admin/config.php';

            if (file_exists($config_file) && file_exists($admin_config_file)) {
                $paths[] = realpath($current_dir);
                break;
            }

            $current_dir = dirname($current_dir);
        }
    }

    return $paths;
}

// Используем функцию для определения путей
$opencart_paths = find_opencart_paths();
if (!empty($opencart_paths)) {
    define('OPENCART_PATHS', $opencart_paths);
    define('OPENCART_DIR', $opencart_paths[0]); // Основной путь (для совместимости)
    if (count($opencart_paths) > 1) {
        echo "Найдено несколько путей OpenCart: " . implode(', ', $opencart_paths) . "\n";
    }
} else {
    echo "Ошибка: Путь к OpenCart не найден.\n";
    define('OPENCART_PATHS', [dirname(dirname(CURRENT_DIR))]);
    define('OPENCART_DIR', dirname(dirname(CURRENT_DIR))); 
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