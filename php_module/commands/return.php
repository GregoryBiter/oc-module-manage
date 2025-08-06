<?php
/**
 * Команда возврата файлов из OpenCart в модуль.
 * Обратная операция к dev и install.
 */
function return_files($args = []) {
    // Проверяем существование директории OpenCart
    if (!is_dir(OPENCART_DIR)) {
        echo "Ошибка: Директория OpenCart не существует: " . OPENCART_DIR . "\n";
        echo "Проверьте файл .path-opencart или путь к установке OpenCart.\n";
        return;
    }
    
    if (!file_exists(FILES_JSON)) {
        echo "Файл .ocm_files.json не найден. Выполните команду init сначала.\n";
        return;
    }
    
    $files = load_files_list();
    
    if (empty($files)) {
        echo "Список файлов пуст в .ocm_files.json. Нечего возвращать.\n";
        return;
    }
    
    // Определяем, какие файлы нужно вернуть
    $files_to_return = [];
    
    // Если переданы аргументы с шаблонами, используем их
    if (!empty($args)) {
        foreach ($args as $pattern) {
            $matched = false;
            foreach ($files as $file) {
                if (match_wildcard_pattern($pattern, $file)) {
                    if (strpos($file, '*') !== false) {
                        // Если это шаблон с *, находим соответствующие реальные файлы
                        $real_files = resolve_wildcard_pattern($file);
                        if (!empty($real_files)) {
                            $files_to_return = array_merge($files_to_return, $real_files);
                            $matched = true;
                        }
                    } else {
                        $files_to_return[] = $file;
                        $matched = true;
                    }
                }
            }
            if (!$matched) {
                echo "Предупреждение: шаблон '{$pattern}' не соответствует ни одному файлу в списке.\n";
            }
        }
    } else {
        // Если аргументы не переданы, обрабатываем все файлы и шаблоны
        foreach ($files as $file) {
            if (strpos($file, '*') !== false) {
                // Для шаблонов с * находим соответствующие реальные файлы
                $real_files = resolve_wildcard_pattern($file);
                if (!empty($real_files)) {
                    $files_to_return = array_merge($files_to_return, $real_files);
                }
            } else {
                $files_to_return[] = $file;
            }
        }
    }
    
    // Удаляем дубликаты
    $files_to_return = array_unique($files_to_return);
    
    if (empty($files_to_return)) {
        echo "Нет файлов, соответствующих указанным шаблонам.\n";
        return;
    }
    
    echo "Возвращаем файлы из OpenCart в модуль...\n";
    $copied_count = 0;
    $skipped_count = 0;
    $errors_count = 0;
    
    // Проверяем существование директории модуля
    if (!is_dir(MODULE_DIR)) {
        echo "Создаем директорию модуля...\n";
        mkdir(MODULE_DIR, 0777, true);
    }
    
    foreach ($files_to_return as $file) {
        $src_path = OPENCART_DIR . '/' . $file;
        $dest_path = MODULE_DIR . '/' . $file;
        
        if (!file_exists($src_path)) {
            echo "Пропущен (не найден в OpenCart): {$file}\n";
            $skipped_count++;
            continue;
        }
        
        // Создаем директорию для файла, если не существует
        $dest_dir = dirname($dest_path);
        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0777, true);
        }
        
        if (copy($src_path, $dest_path)) {
            echo "Возвращен: {$file}\n";
            $copied_count++;
        } else {
            echo "Ошибка при копировании: {$file}\n";
            $errors_count++;
        }
    }
    
    echo "\nОперация завершена:\n";
    echo "- Возвращено файлов: {$copied_count}\n";
    echo "- Пропущено файлов: {$skipped_count}\n";
    echo "- Ошибок: {$errors_count}\n";
}

/**
 * Разрешает шаблон с подстановочными символами * на реальные файлы
 * 
 * @param string $pattern Шаблон с подстановочными символами *
 * @return array Список реальных файлов, соответствующих шаблону
 */
function resolve_wildcard_pattern($pattern) {
    $result = [];
    
    // Преобразуем шаблон в регулярное выражение для поиска
    $regex_pattern = str_replace(
        ['.', '*'], 
        ['\.', '(.*)'], 
        $pattern
    );
    $regex_pattern = '#^' . $regex_pattern . '$#';
    
    // Определяем базовую часть пути до первой звездочки
    $base_path = substr($pattern, 0, strpos($pattern, '*'));
    $base_dir = OPENCART_DIR . '/' . dirname($base_path);
    
    // Если базовая директория не существует, возвращаем пустой массив
    if (!is_dir($base_dir)) {
        return $result;
    }
    
    // Рекурсивно находим все файлы в OpenCart
    $all_files = find_all_files(OPENCART_DIR, OPENCART_DIR);
    
    // Проверяем каждый файл на соответствие регулярному выражению
    foreach ($all_files as $file) {
        if (preg_match($regex_pattern, $file)) {
            $result[] = $file;
        }
    }
    
    return $result;
}
