<?php

/* Загружает данные из JSON файла.
 */
function load_json() {
    if (file_exists(JSON_FILE)) {
        return json_decode(file_get_contents(JSON_FILE), true);
    }
    return [];
}

/**
 * Сохраняет данные в JSON файл.
 */
function save_json($data) {
    file_put_contents(JSON_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Загружает список файлов из файла .ocm_files.json
 */
function load_files_list() {
    if (file_exists(FILES_JSON)) {
        $data = json_decode(file_get_contents(FILES_JSON), true);
        return isset($data['files']) ? $data['files'] : [];
    }
    return [];
}

/**
 * Сохраняет список файлов в файл .ocm_files.json
 */
function save_files_list($files) {
    $data = ['files' => $files];
    file_put_contents(FILES_JSON, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Загружает метаданные модуля из opencart-module.json
 */
function load_module_metadata() {
    if (file_exists(JSON_FILE)) {
        $json = file_get_contents(JSON_FILE);
        $data = json_decode($json, true);
        if ($data) {
            // Исключаем поле files, если оно есть
            unset($data['files']);
            return $data;
        }
    }
    return [];
}

/**
 * Сохраняет метаданные модуля в opencart-module.json
 */
function save_module_metadata($metadata) {
    // Убеждаемся, что поле files не попадет в метаданные
    unset($metadata['files']);
    
    // Если файл уже существует, пробуем сохранить форматирование или просто перезаписать
    file_put_contents(JSON_FILE, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Инициализация OpenCart окружения
 */
function bootstrap_opencart() {
    if (!defined('OPENCART_DIR') || !is_dir(OPENCART_DIR)) {
        return null;
    }

    $config_file = OPENCART_DIR . '/config.php';
    if (!file_exists($config_file)) {
        return null;
    }

    // Попытка загрузить переменные окружения (.env)
    load_env_file(OPENCART_DIR . '/.env');
    load_env_file(CURRENT_DIR . '/.env');

    // Если OC_PATH не задан в окружении, устанавливаем его в OPENCART_DIR
    if (!isset($_ENV['OC_PATH']) || empty($_ENV['OC_PATH'])) {
        $_ENV['OC_PATH'] = OPENCART_DIR;
    }

    // Загружаем конфиг
    require_once $config_file;

    // Проверка необходимых констант
    if (!defined('DIR_SYSTEM')) {
        return null;
    }

    // Загрузка OpenCart
    require_once DIR_SYSTEM . 'startup.php';

    // Registry
    $registry = new Registry();

    // Loader
    $loader = new Loader($registry);
    $registry->set('load', $loader);

    // Database
    if (defined('DB_DRIVER') && defined('DB_HOSTNAME') && defined('DB_USERNAME') && defined('DB_DATABASE')) {
        try {
            $db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, defined('DB_PORT') ? DB_PORT : NULL);
            $registry->set('db', $db);
            return $registry;
        } catch (\Exception $e) {
            echo "Ошибка подключения к БД: " . $e->getMessage() . "\n";
            return null;
        }
    }

    return null;
}

/**
 * Загрузка переменных из .env файла
 */
function load_env_file($path) {
    if (!file_exists($path)) return;
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        if (!isset($_ENV[$name])) {
            $_ENV[$name] = trim($value);
            putenv("{$name}=" . trim($value));
        }
    }
}

/**
 * Получить объект базы данных OpenCart
 */
function get_opencart_db() {
    static $db = null;
    if ($db === null) {
        $registry = bootstrap_opencart();
        if ($registry && $registry->has('db')) {
            $db = $registry->get('db');
        }
    }
    return $db;
}

/**
 * Вспомогательная функция для получения БД по пути
 */
function get_opencart_db_for_path($target_path) {
    static $connections = [];

    $real_target_path = realpath($target_path);
    if (!$real_target_path) {
        return null;
    }

    if (isset($connections[$real_target_path])) {
        return $connections[$real_target_path];
    }

    $config_file = $real_target_path . '/config.php';
    if (!file_exists($config_file)) {
        return null;
    }

    $config_content = file_get_contents($config_file);
    if ($config_content === false) {
        return null;
    }

    $extract = function ($name) use ($config_content) {
        $pattern = "/define\\('\\Q{$name}\\E'\\s*,\\s*'([^']*)'\\)/";
        if (preg_match($pattern, $config_content, $matches)) {
            return $matches[1];
        }
        return null;
    };

    $driver = $extract('DB_DRIVER');
    $hostname = $extract('DB_HOSTNAME');
    $username = $extract('DB_USERNAME');
    $password = $extract('DB_PASSWORD');
    $database = $extract('DB_DATABASE');
    $port = $extract('DB_PORT');

    if (!$driver || !$hostname || !$username || !$database) {
        return null;
    }

    try {
        if (!class_exists('DB')) {
            $db_library = $real_target_path . '/system/library/db.php';
            if (file_exists($db_library)) {
                require_once $db_library;
            }
        }

        $adaptor_class = 'DB\\' . $driver;
        if (!class_exists($adaptor_class)) {
            $adaptor_file = $real_target_path . '/system/library/db/' . $driver . '.php';
            if (file_exists($adaptor_file)) {
                require_once $adaptor_file;
            }
        }

        $db = new DB($driver, $hostname, $username, (string)$password, $database, $port ?: null);
        $connections[$real_target_path] = $db;
        return $db;
    } catch (\Exception $e) {
        echo "Ошибка подключения к БД ({$real_target_path}): " . $e->getMessage() . "\n";
        return null;
    }
}

/**
 * Очистка кеша модификаций
 */
function refresh_modifications($target_path) {
    $mod_dir = $target_path . '/system/storage/modification/';
    if (is_dir($mod_dir)) {
        echo "  Очистка кеша модификаций...\n";
        clean_directory($mod_dir);
    }
}

/**
 * Миграция данных из старого формата в новый
 * Если в opencart-module.json есть поле 'files', перемещает его в .ocm_files.json
 */
function migrate_old_format() {
    if (!file_exists(JSON_FILE)) {
        return false;
    }
    
    $data = json_decode(file_get_contents(JSON_FILE), true);
    if (!$data || !isset($data['files'])) {
        return false; // Нет поля files, миграция не нужна
    }
    
    // Сохраняем файлы в новый формат
    $files = $data['files'];
    save_files_list($files);
    
    // Удаляем поле files из метаданных и сохраняем
    unset($data['files']);
    save_module_metadata($data);
    
    echo "Выполнена миграция данных в новый формат:\n";
    echo "- Список файлов перемещен в .ocm_files.json\n";
    echo "- Метаданные модуля остались в opencart-module.json\n";
    
    return true;
}

/**
 * Преобразование snake_case в CamelCase.
 */
function to_camel_case($snake_str) {
    $components = explode('_', $snake_str);
    return implode('', array_map('ucfirst', $components));
}

/**
 * Преобразование snake_case в camelCase.
 */
function to_camel_case_lower($snake_str) {
    $components = explode('_', $snake_str);
    $first = array_shift($components);
    return $first . implode('', array_map('ucfirst', $components));
}


/**
 * Рекурсивный поиск всех файлов в директории.
 */
function find_all_files($dir, $base_dir = null) {
    if ($base_dir === null) $base_dir = $dir;
    $files = [];
    if (!is_dir($dir)) return $files;
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $files = array_merge($files, find_all_files($path, $base_dir));
        } else {
            $relative_path = substr($path, strlen($base_dir) + 1);
            $files[] = $relative_path;
        }
    }
    return $files;
}


/**
 * Рекурсивное копирование директории с заменой плейсхолдеров
 */
function copy_dir_recursively($src, $dst, $module_name, $camel_case_name, $camel_case_lower_name) {
    if (!is_dir($src)) return;
    if (!is_dir($dst)) mkdir($dst, 0777, true);
    
    $items = scandir($src);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $src_path = $src . '/' . $item;
        
        // Замена в имени файла/директории
        $new_item = str_replace(
            ['{{#ModuleName}}', '{{#moduleName}}', '{{#module_name}}'], 
            [$camel_case_name, $camel_case_lower_name, $module_name], 
            $item
        );
        
        $dst_path = $dst . '/' . $new_item;
        
        if (is_dir($src_path)) {
            copy_dir_recursively($src_path, $dst_path, $module_name, $camel_case_name, $camel_case_lower_name);
        } else {
            // Копирование файла с заменой в содержимом
            $content = file_get_contents($src_path);
            $content = str_replace(
                ['{{#ModuleName}}', '{{#moduleName}}', '{{#module_name}}'], 
                [$camel_case_name, $camel_case_lower_name, $module_name], 
                $content
            );
            file_put_contents($dst_path, $content);
        }
    }
}


/**
 * Синхронизация файла.
 */
function sync_file($relative_path) {
    global $last_modified_times;
    
    $src_path = MODULE_DIR . '/' . $relative_path;
    $dest_path = OPENCART_DIR . '/' . $relative_path;
    
    // Проверка времени последней модификации
    $current_time = filemtime($src_path);
    if (isset($last_modified_times[$relative_path]) && $last_modified_times[$relative_path] == $current_time) {
        return;
    }
    
    $last_modified_times[$relative_path] = $current_time;
    
    // Создание директории для назначения
    $dest_dir = dirname($dest_path);
    if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0777, true);
    }
    
    if (copy($src_path, $dest_path)) {
        echo "Синхронизировано: {$dest_path}\n";
        
        // Обновляем список файлов
        $files = load_files_list();
        if (!in_array($relative_path, $files)) {
            $files[] = $relative_path;
            sort($files); // Сортируем для удобства чтения
            save_files_list($files);
            echo "Файл {$relative_path} добавлен в список отслеживаемых.\n";
        }
    } else {
        echo "Ошибка при синхронизации файла: {$dest_path}\n";
    }
}

/**
 * Удаление файла.
 */
function remove_file($relative_path) {
    $dest_path = OPENCART_DIR . '/' . $relative_path;
    
    if (file_exists($dest_path)) {
        unlink($dest_path);
        echo "Удалено: {$dest_path}\n";
    } else {
        echo "Пропущено (файл не найден): {$dest_path}\n";
    }
    
    // Удаление пустых папок
    $parent_dir = dirname($dest_path);
    while ($parent_dir != OPENCART_DIR) {
        if (is_dir($parent_dir) && count(scandir($parent_dir)) <= 2) {
            rmdir($parent_dir);
            echo "Удалена пустая папка: " . substr($parent_dir, strlen(OPENCART_DIR) + 1) . "\n";
        } else {
            break;
        }
        $parent_dir = dirname($parent_dir);
    }
    
    // Обновляем список файлов
    $files = load_files_list();
    $key = array_search($relative_path, $files);
    if ($key !== false) {
        unset($files[$key]);
        $files = array_values($files);
        save_files_list($files);
    }
}



/**
 * Поиск файлов по шаблону
 */
function find_files_by_pattern($pattern) {
    $files = [];
    
    // Заменяем ** на специальный маркер для последующей обработки
    $pattern = str_replace('**', '{{ALL_SUBDIRS}}', $pattern);
    
    // Заменяем * на регулярное выражение
    $pattern = str_replace('*', '{{ANY_FILES}}', $pattern);
    
    // Если в шаблоне есть маркер для всех поддиректорий
    if (strpos($pattern, '{{ALL_SUBDIRS}}') !== false) {
        $parts = explode('{{ALL_SUBDIRS}}', $pattern);
        $base_dir = rtrim($parts[0], '/');
        $suffix = isset($parts[1]) ? ltrim($parts[1], '/') : '';
        
        // Рекурсивно находим все файлы в базовой директории
        $all_files = find_all_files_relative(CURRENT_DIR . '/' . $base_dir, CURRENT_DIR);
        
        foreach ($all_files as $file) {
            if (empty($suffix) || (strpos($file, $suffix) !== false && strpos($file, $suffix) === (strlen($file) - strlen($suffix)))) {
                $files[] = $file;
            }
        }
    } 
    // Если есть маркер для любых файлов в директории
    elseif (strpos($pattern, '{{ANY_FILES}}') !== false) {
        $dir_pattern = str_replace('{{ANY_FILES}}', '*', $pattern);
        $matched_files = glob(CURRENT_DIR . '/' . $dir_pattern);
        
        foreach ($matched_files as $file) {
            if (is_file($file)) {
                $files[] = substr($file, strlen(CURRENT_DIR) + 1);
            }
        }
    }
    // Простое копирование конкретного файла
    else {
        if (file_exists(CURRENT_DIR . '/' . $pattern)) {
            $files[] = $pattern;
        }
    }
    
    return $files;
}

/**
 * Рекурсивный поиск всех файлов в директории (возвращает относительные пути)
 */
function find_all_files_relative($dir, $base_dir) {
    $files = [];
    if (!is_dir($dir)) return $files;
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $sub_files = find_all_files_relative($path, $base_dir);
            $files = array_merge($files, $sub_files);
        } else {
            $files[] = substr($path, strlen($base_dir) + 1);
        }
    }
    return $files;
}

/**
 * Очистка директории без удаления самой директории
 */
function clean_directory($dir) {
    if (!is_dir($dir)) return;
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            clean_directory($path);
            rmdir($path);
        } else {
            unlink($path);
        }
    }
}

/**
 * Проверить наличие расширения ZIP
 */
function check_zip_extension() {
    if (!extension_loaded('zip')) {
        echo "ОШИБКА: Расширение PHP ZIP не установлено. Архивирование невозможно.\n";
        echo "Установите расширение командой: sudo apt-get install php-zip\n";
        return false;
    }
    return true;
}

/**
 * Проверяет соответствие строки шаблону с подстановочными символами *
 * 
 * @param string $pattern Шаблон с подстановочными символами *
 * @param string $string Проверяемая строка
 * @return bool true если строка соответствует шаблону, иначе false
 */
function match_wildcard_pattern($pattern, $string) {
    // Преобразуем шаблон в регулярное выражение
    $regex = str_replace(
        ['.', '*'], 
        ['\.', '.*'], 
        $pattern
    );
    return preg_match('#^' . $regex . '$#', $string) === 1;
}
function get_install_xml_path() {
    return CURRENT_DIR . '/install.xml';
}

function parse_install_xml_metadata() {
    $xml_file = get_install_xml_path();
    if (!is_file($xml_file)) {
        return null;
    }

    $xml_content = file_get_contents($xml_file);
    if ($xml_content === false || $xml_content === '') {
        return null;
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    if (!@$dom->loadXML($xml_content)) {
        return null;
    }

    $read = function($tag, $default = '') use ($dom) {
        $node = $dom->getElementsByTagName($tag)->item(0);
        return $node ? trim($node->nodeValue) : $default;
    };

    return [
        'xml' => $xml_content,
        'code' => $read('code', ''),
        'name' => $read('name', ''),
        'version' => $read('version', ''),
        'author' => $read('author', ''),
        'link' => $read('link', '')
    ];
}

function validate_module_metadata_contract(&$metadata, &$errors) {
    $errors = [];
    if (!is_array($metadata)) {
        $errors[] = 'opencart-module.json должен содержать объект JSON';
        return false;
    }

    if (array_key_exists('files', $metadata)) {
        $errors[] = "Поле 'files' запрещено в opencart-module.json (используйте .ocm_files.json)";
    }

    return empty($errors);
}

function infer_code_from_metadata($metadata) {
    if (!is_array($metadata)) {
        return basename(CURRENT_DIR);
    }

    if (!empty($metadata['controller'])) {
        $controller = trim((string)$metadata['controller'], '/');
        $parts = explode('/', $controller);
        $type = count($parts) >= 3 ? $parts[count($parts) - 2] : 'module';
        $name = count($parts) >= 1 ? $parts[count($parts) - 1] : basename(CURRENT_DIR);
        return preg_replace('/[^a-z0-9_]+/i', '_', strtolower($type . '_' . $name));
    }

    if (!empty($metadata['type']) && !empty($metadata['name'])) {
        return preg_replace('/[^a-z0-9_]+/i', '_', strtolower($metadata['type'] . '_' . $metadata['name']));
    }

    return basename(CURRENT_DIR);
}

function get_existing_modification_version($db, $code) {
    $safe_code = $db->escape($code);
    $query = $db->query("SELECT `version` FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $safe_code . "' LIMIT 1");
    if ($query->num_rows > 0 && !empty($query->row['version'])) {
        return $query->row['version'];
    }
    return '';
}

function resolve_module_identity($target_path) {
    $metadata = load_module_metadata();
    $errors = [];
    validate_module_metadata_contract($metadata, $errors);

    $install_xml = parse_install_xml_metadata();
    $db = get_opencart_db_for_path($target_path);

    $code = !empty($metadata['code']) ? $metadata['code'] : '';
    if ($code === '' && $install_xml && !empty($install_xml['code'])) {
        $code = $install_xml['code'];
    }
    if ($code === '') {
        $code = infer_code_from_metadata($metadata);
    }

    $name = '';
    if (!empty($metadata['module_name'])) {
        $name = $metadata['module_name'];
    } elseif (!empty($metadata['name'])) {
        $name = $metadata['name'];
    } elseif ($install_xml && !empty($install_xml['name'])) {
        $name = $install_xml['name'];
    } else {
        $name = $code;
    }

    $version = '';
    if (!empty($metadata['version'])) {
        $version = $metadata['version'];
    } elseif ($install_xml && !empty($install_xml['version'])) {
        $version = $install_xml['version'];
    } elseif ($db) {
        $version = get_existing_modification_version($db, $code);
    }
    if ($version === '') {
        $version = '0.0.0';
    }

    return [
        'code' => $code,
        'name' => $name,
        'version' => $version,
        'metadata' => is_array($metadata) ? $metadata : [],
        'install_xml' => $install_xml,
        'errors' => $errors
    ];
}

function ensure_ocm_tables($db) {
    $db->query("
        CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ocm_modules` (
            `module_id` INT(11) NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(64) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `type` VARCHAR(32) NOT NULL DEFAULT 'module',
            `installed_version` VARCHAR(32) NOT NULL DEFAULT '0.0.0',
            `source` VARCHAR(32) NOT NULL DEFAULT 'ocm_cli',
            `metadata_json` MEDIUMTEXT NOT NULL,
            `status` TINYINT(1) NOT NULL DEFAULT 1,
            `installed_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`module_id`),
            UNIQUE KEY `code` (`code`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ocm_module_files` (
            `file_id` INT(11) NOT NULL AUTO_INCREMENT,
            `module_code` VARCHAR(64) NOT NULL,
            `file_path` VARCHAR(500) NOT NULL,
            `file_hash` VARCHAR(64) NOT NULL DEFAULT '',
            `installed_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            `removed_at` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`file_id`),
            KEY `module_code` (`module_code`),
            KEY `file_path` (`file_path`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ocm_module_versions` (
            `version_id` INT(11) NOT NULL AUTO_INCREMENT,
            `module_code` VARCHAR(64) NOT NULL,
            `version` VARCHAR(32) NOT NULL,
            `package_hash` VARCHAR(64) NOT NULL DEFAULT '',
            `index_hash` VARCHAR(64) NOT NULL DEFAULT '',
            `changelog` TEXT NOT NULL,
            `source` VARCHAR(32) NOT NULL DEFAULT 'ocm_cli',
            `published_at` DATETIME NOT NULL,
            `applied_at` DATETIME NOT NULL,
            PRIMARY KEY (`version_id`),
            KEY `module_code` (`module_code`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ocm_update_packages` (
            `package_id` INT(11) NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(64) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `version` VARCHAR(32) NOT NULL,
            `author` VARCHAR(255) DEFAULT NULL,
            `author_url` VARCHAR(255) DEFAULT NULL,
            `category` VARCHAR(64) DEFAULT 'module',
            `opencart_version` VARCHAR(32) DEFAULT NULL,
            `dependencies` TEXT DEFAULT NULL,
            `archive_structure` enum('opencart', 'direct') DEFAULT 'opencart',
            `file_path` VARCHAR(500) NOT NULL,
            `file_size` INT(11) DEFAULT 0,
            `file_hash` VARCHAR(64) DEFAULT NULL,
            `package_hash` VARCHAR(64) DEFAULT NULL,
            `index_hash` VARCHAR(64) DEFAULT NULL,
            `image` VARCHAR(255) DEFAULT NULL,
            `demo_url` VARCHAR(255) DEFAULT NULL,
            `documentation_url` VARCHAR(255) DEFAULT NULL,
            `support_url` VARCHAR(255) DEFAULT NULL,
            `price` DECIMAL(15,4) DEFAULT 0.0000,
            `downloads` INT(11) DEFAULT 0,
            `rating` DECIMAL(3,2) DEFAULT 0.00,
            `reviews` INT(11) DEFAULT 0,
            `status` TINYINT(1) DEFAULT 1,
            `featured` TINYINT(1) DEFAULT 0,
            `sort_order` INT(3) DEFAULT 0,
            `date_added` DATETIME NOT NULL,
            `date_modified` DATETIME NOT NULL,
            PRIMARY KEY (`package_id`),
            UNIQUE KEY `code` (`code`),
            KEY `status` (`status`),
            KEY `featured` (`featured`),
            KEY `category` (`category`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
    ");
}

/**
 * Обработка OCMOD файла (install.xml)
 */
function handle_ocmod($target_path) {
    $install_xml = parse_install_xml_metadata();
    if (!$install_xml) {
        echo "  Ошибка: install.xml обязателен и должен быть валидным XML.\n";
        return false;
    }

    $db = get_opencart_db_for_path($target_path);
    if (!$db) {
        echo "  Предупреждение: Не удалось подключиться к БД для установки модификатора.\n";
        return false;
    }

    $code = $install_xml['code'] !== '' ? $install_xml['code'] : basename(CURRENT_DIR);
    $name = $install_xml['name'] !== '' ? $install_xml['name'] : $code;
    $version = $install_xml['version'] !== '' ? $install_xml['version'] : '0.0.0';
    $author = $install_xml['author'] !== '' ? $install_xml['author'] : 'Unknown';
    $link = $install_xml['link'];

    $db->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $db->escape($code) . "'");
    $db->query("INSERT INTO `" . DB_PREFIX . "modification` SET 
        `code` = '" . $db->escape($code) . "',
        `name` = '" . $db->escape($name) . "',
        `author` = '" . $db->escape($author) . "',
        `version` = '" . $db->escape($version) . "',
        `link` = '" . $db->escape($link) . "',
        `xml` = '" . $db->escape($install_xml['xml']) . "',
        `status` = 1,
        `date_added` = NOW()");

    echo "  Модификатор '{$code}' установлен из install.xml.\n";
    refresh_modifications($target_path);
    return true;
}

function remove_module_from_db($target_path, $module_code) {
    $db = get_opencart_db_for_path($target_path);
    if (!$db || !$module_code) {
        return;
    }

    ensure_ocm_tables($db);

    $safe_code = $db->escape($module_code);
    $db->query("DELETE FROM `" . DB_PREFIX . "ocm_modules` WHERE `code` = '" . $safe_code . "'");
    $db->query("UPDATE `" . DB_PREFIX . "ocm_module_files` SET `removed_at` = NOW() WHERE `module_code` = '" . $safe_code . "' AND `removed_at` IS NULL");
}

/**
 * Синхронизация данных о модуле с базой OpenCart
 */
function sync_with_db($target_path, $files) {
    $db = get_opencart_db_for_path($target_path);
    if (!$db) {
        return;
    }

    ensure_ocm_tables($db);

    $identity = resolve_module_identity($target_path);
    $metadata = $identity['metadata'];

    if (!empty($identity['errors'])) {
        foreach ($identity['errors'] as $error) {
            echo "  Ошибка контракта: {$error}\n";
        }
        return;
    }

    $code = $identity['code'];
    $name = $identity['name'];
    $version = $identity['version'];
    $module_type = !empty($metadata['type']) ? $metadata['type'] : 'module';
    $install_xml_hash = $identity['install_xml'] ? sha1($identity['install_xml']['xml']) : '';

    $metadata['code'] = $code;
    $metadata['version'] = $version;
    $metadata['name'] = $name;

    $db->query("INSERT INTO `" . DB_PREFIX . "ocm_modules` SET
        `code` = '" . $db->escape($code) . "',
        `name` = '" . $db->escape($name) . "',
        `type` = '" . $db->escape($module_type) . "',
        `installed_version` = '" . $db->escape($version) . "',
        `source` = 'ocm_cli',
        `metadata_json` = '" . $db->escape(json_encode($metadata, JSON_UNESCAPED_UNICODE)) . "',
        `status` = 1,
        `installed_at` = NOW(),
        `updated_at` = NOW()
        ON DUPLICATE KEY UPDATE
        `name` = VALUES(`name`),
        `type` = VALUES(`type`),
        `installed_version` = VALUES(`installed_version`),
        `source` = 'ocm_cli',
        `metadata_json` = VALUES(`metadata_json`),
        `status` = 1,
        `updated_at` = NOW()");

    $safe_code = $db->escape($code);
    $db->query("DELETE FROM `" . DB_PREFIX . "ocm_module_files` WHERE `module_code` = '" . $safe_code . "'");

    foreach ($files as $relative_path) {
        $target_file = rtrim($target_path, '/') . '/' . ltrim($relative_path, '/');
        $file_hash = is_file($target_file) ? sha1_file($target_file) : '';

        $db->query("INSERT INTO `" . DB_PREFIX . "ocm_module_files` SET
            `module_code` = '" . $safe_code . "',
            `file_path` = '" . $db->escape($relative_path) . "',
            `file_hash` = '" . $db->escape($file_hash ?: '') . "',
            `installed_at` = NOW(),
            `updated_at` = NOW(),
            `removed_at` = NULL");
    }

    $db->query("INSERT INTO `" . DB_PREFIX . "ocm_module_versions` SET
        `module_code` = '" . $safe_code . "',
        `version` = '" . $db->escape($version) . "',
        `package_hash` = '',
        `index_hash` = '" . $db->escape($install_xml_hash) . "',
        `changelog` = '',
        `source` = 'ocm_cli',
        `published_at` = NOW(),
        `applied_at` = NOW()");

    echo "  Данные модуля синхронизированы в таблицы ocm_*.\n";
}
