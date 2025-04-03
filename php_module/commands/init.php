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

    // Получаем список файлов
    $files = find_all_files(MODULE_DIR, MODULE_DIR);
    
    if (empty($files)) {
        echo "Файлы не найдены в директории модуля. Проверьте наличие файлов в директории 'upload'.\n";
    } else {
        // Сортировка для удобства чтения
        sort($files);
        $data['files'] = $files;
        save_json($data);
        
        echo "Найдено и инициализировано файлов: " . count($files) . "\n";
        if (count($files) <= 20) {
            echo implode("\n", $files) . "\n";
        } else {
            echo "Первые 20 файлов:\n" . implode("\n", array_slice($files, 0, 20)) . "\n... и еще " . (count($files) - 20) . " файлов\n";
        }
    }
}
