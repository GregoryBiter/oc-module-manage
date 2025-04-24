<?php
/**
 * Копирование файлов модуля в папку OpenCart и обновление JSON.
 */
function install($args = []) {
    // Проверяем существование директории OpenCart
    if (!is_dir(OPENCART_DIR)) {
        echo "Ошибка: Директория OpenCart не существует: " . OPENCART_DIR . "\n";
        echo "Проверьте файл .path-opencart или путь к установке OpenCart.\n";
        return;
    }

    $data = load_json();
    $files = isset($data['files']) ? $data['files'] : [];
    $new_files = [];

    foreach (find_all_files(MODULE_DIR, MODULE_DIR) as $relative_path) {
        $src_path = MODULE_DIR . '/' . $relative_path;
        $dest_path = OPENCART_DIR . '/' . $relative_path;
        
        // Создание директории если не существует
        $dest_dir = dirname($dest_path);
        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0777, true);
        }
        
        copy($src_path, $dest_path);
        echo "Копирование {$relative_path} -> " . substr($dest_path, strlen(OPENCART_DIR) + 1) . "\n";
        
        if (!in_array($relative_path, $files)) {
            $new_files[] = $relative_path;
        }
    }

    if (!empty($new_files)) {
        $data['files'] = array_merge($files, $new_files);
        save_json($data);
        echo "Добавлены новые файлы: " . implode(', ', $new_files) . "\n";
    }
    echo "Установка завершена.\n";
}