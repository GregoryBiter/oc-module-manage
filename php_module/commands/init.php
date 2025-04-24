<?php

/**
 * Инициализация списка файлов и запись в JSON.
 */
function init($args = []) {
    if (file_exists(JSON_FILE)) {
        $data = load_json();
        echo "Файл opencart-module.json уже существует. Обновляем список файлов.\n";
    } else {
        echo "Введите имя модуля: ";
        $module_name = trim(fgets(STDIN));
        echo "Введите имя создателя: ";
        $creator_name = trim(fgets(STDIN));
        echo "Введите email создателя: ";
        $creator_email = trim(fgets(STDIN));
        
        $data = [
            'module_name' => $module_name,
            'creator_name' => $creator_name,
            'creator_email' => $creator_email,
            'files' => []
        ];
    }

    // Проверяем существование директории модуля
    if (!is_dir(MODULE_DIR)) {
        echo "Директория модуля не найдена. Создаем директорию 'upload'...\n";
        mkdir(MODULE_DIR, 0777, true);
    }

    // Получаем текущий список файлов
    $current_files = find_all_files(MODULE_DIR, MODULE_DIR);
    $existing_files = isset($data['files']) ? $data['files'] : [];
    
    // Отделяем обычные файлы от шаблонов с символом *
    $wildcard_patterns = [];
    $regular_files = [];
    foreach ($existing_files as $file) {
        if (strpos($file, '*') !== false) {
            $wildcard_patterns[] = $file;
        } else {
            $regular_files[] = $file;
        }
    }
    
    // Находим новые файлы (те, которых нет в списке обычных файлов и не соответствуют шаблонам)
    $new_files = [];
    $matched_by_pattern = [];
    foreach ($current_files as $file) {
        $found = in_array($file, $regular_files);
        
        if (!$found) {
            // Проверяем, соответствует ли файл какому-либо шаблону
            $pattern_matched = false;
            foreach ($wildcard_patterns as $pattern) {
                if (match_wildcard_pattern($pattern, $file)) {
                    $pattern_matched = true;
                    $matched_by_pattern[] = $file;
                    break;
                }
            }
            
            if (!$pattern_matched) {
                $new_files[] = $file;
            }
        }
    }
    
    // Находим удаленные файлы (те, которых больше нет в директории)
    $deleted_files = [];
    foreach ($regular_files as $file) {
        if (!in_array($file, $current_files)) {
            $deleted_files[] = $file;
        }
    }
    
    // Обновляем список файлов
    $updated_files = array_merge(
        array_diff($regular_files, $deleted_files), // Существующие обычные файлы без удаленных
        $new_files,                                 // Новые файлы
        $wildcard_patterns                          // Сохраняем все шаблоны
    );
    
    // Сортируем список для удобства чтения
    sort($updated_files);
    $data['files'] = $updated_files;
    save_json($data);
    
    // Выводим информацию об обновлении
    echo "Обновление списка файлов завершено:\n";
    if (!empty($new_files)) {
        echo "- Добавлено новых файлов: " . count($new_files) . "\n";
        foreach ($new_files as $file) {
            echo "  + " . $file . "\n";
        }
    }
    if (!empty($deleted_files)) {
        echo "- Удалено отсутствующих файлов: " . count($deleted_files) . "\n";
        foreach ($deleted_files as $file) {
            echo "  - " . $file . "\n";
        }
    }
    if (!empty($matched_by_pattern)) {
        echo "- Файлов, соответствующих шаблонам: " . count($matched_by_pattern) . "\n";
    }
    if (!empty($wildcard_patterns)) {
        echo "- Сохранено шаблонов: " . count($wildcard_patterns) . "\n";
        foreach ($wildcard_patterns as $pattern) {
            echo "  * " . $pattern . "\n";
        }
    }
    
    echo "Всего файлов в списке: " . count($updated_files) . "\n";
}
