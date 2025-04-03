<?php

/**
 * Режим наблюдения за изменениями.
 */
function dev($args = []) {
    global $last_modified_times;
    
    // Проверяем существование директории OpenCart
    if (!is_dir(OPENCART_DIR)) {
        echo "Ошибка: Директория OpenCart не существует: " . OPENCART_DIR . "\n";
        echo "Проверьте файл .path-opencart или путь к установке OpenCart.\n";
        return;
    }
    
    echo "Запущен режим наблюдения. Нажмите Ctrl+C для выхода.\n";
    echo "Используется путь к OpenCart: " . OPENCART_DIR . "\n";
    
    // Загружаем данные из JSON
    $data = load_json();
    $tracked_files = isset($data['files']) ? $data['files'] : [];
    
    // Получаем начальное состояние файлов
    $files_map = [];
    $all_files = find_all_files(MODULE_DIR, MODULE_DIR);
    
    // Добавляем новые файлы в отслеживание, если их нет в JSON
    $new_files = array_diff($all_files, $tracked_files);
    if (!empty($new_files)) {
        echo "Найдено новых файлов: " . count($new_files) . "\n";
        foreach ($new_files as $file) {
            echo "Добавлен для отслеживания: {$file}\n";
            $tracked_files[] = $file;
        }
        
        // Сортируем и обновляем список файлов в JSON
        sort($tracked_files);
        $data['files'] = $tracked_files;
        save_json($data);
        echo "Список отслеживаемых файлов обновлен в JSON.\n";
    }
    
    // Инициализируем карту времени модификации
    foreach ($all_files as $file) {
        $full_path = MODULE_DIR . '/' . $file;
        $files_map[$file] = filemtime($full_path);
        $last_modified_times[$file] = $files_map[$file];
    }
    
    echo "Отслеживается файлов: " . count($tracked_files) . "\n";
    
    // Основной цикл наблюдения
    while (true) {
        // Находим текущие файлы
        $current_files = find_all_files(MODULE_DIR, MODULE_DIR);
        $current_files_map = [];
        
        foreach ($current_files as $file) {
            $full_path = MODULE_DIR . '/' . $file;
            $current_files_map[$file] = filemtime($full_path);
        }
        
        // Ищем добавленные/измененные файлы
        foreach ($current_files_map as $file => $mtime) {
            if (!isset($files_map[$file]) || $files_map[$file] != $mtime) {
                // Синхронизируем файл
                sync_file($file);
                $files_map[$file] = $mtime;
                $last_modified_times[$file] = $mtime;
                
                // Если это новый файл, добавляем его в JSON
                if (!in_array($file, $tracked_files)) {
                    $tracked_files[] = $file;
                    sort($tracked_files);
                    $data['files'] = $tracked_files;
                    save_json($data);
                    echo "Файл {$file} добавлен в список отслеживаемых.\n";
                }
            }
        }
        
        // Ищем удаленные файлы
        foreach ($files_map as $file => $mtime) {
            if (!isset($current_files_map[$file])) {
                // Удаляем файл
                remove_file($file);
                unset($files_map[$file]);
                unset($last_modified_times[$file]);
                
                // Удаляем из списка отслеживаемых
                $index = array_search($file, $tracked_files);
                if ($index !== false) {
                    unset($tracked_files[$index]);
                    $tracked_files = array_values($tracked_files);
                    $data['files'] = $tracked_files;
                    save_json($data);
                    echo "Файл {$file} удален из списка отслеживаемых.\n";
                }
            }
        }
        
        sleep(1);
    }
}