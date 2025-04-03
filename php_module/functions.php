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
        
        // Обновляем JSON
        $data = load_json();
        $files = isset($data['files']) ? $data['files'] : [];
        if (!in_array($relative_path, $files)) {
            $files[] = $relative_path;
            sort($files); // Сортируем для удобства чтения
            $data['files'] = $files;
            save_json($data);
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
    
    // Обновляем JSON
    $data = load_json();
    $files = isset($data['files']) ? $data['files'] : [];
    $key = array_search($relative_path, $files);
    if ($key !== false) {
        unset($files[$key]);
        $data['files'] = array_values($files);
        save_json($data);
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