<?php
/**
 * Команда возврата файлов из OpenCart в модуль.
 * Обратная операция к dev и install.
 */
function return_files($args = []) {
    if (!file_exists(JSON_FILE)) {
        echo "Файл opencart-module.json не найден. Выполните команду init сначала.\n";
        return;
    }
    
    $data = load_json();
    $files = isset($data['files']) ? $data['files'] : [];
    
    if (empty($files)) {
        echo "Список файлов пуст в opencart-module.json. Нечего возвращать.\n";
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
    
    foreach ($files as $file) {
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
