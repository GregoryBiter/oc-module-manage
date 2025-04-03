<?php

/**
 * Удаление файлов из OpenCart на основе JSON.
 */
function remove($args = []) {
    $data = load_json();
    $files = isset($data['files']) ? $data['files'] : [];
    
    foreach ($files as $relative_path) {
        $target_file = OPENCART_DIR . '/' . $relative_path;
        if (file_exists($target_file)) {
            unlink($target_file);
            echo "Удалено: {$relative_path}\n";
        } else {
            echo "Пропущено (файл не найден): {$relative_path}\n";
        }
        
        // Удаление пустых папок
        $parent_dir = dirname($target_file);
        while ($parent_dir != OPENCART_DIR) {
            if (is_dir($parent_dir) && count(scandir($parent_dir)) <= 2) {
                rmdir($parent_dir);
                echo "Удалена пустая папка: " . substr($parent_dir, strlen(OPENCART_DIR) + 1) . "\n";
            } else {
                break;
            }
            $parent_dir = dirname($parent_dir);
        }
    }
    echo "Удаление завершено.\n";
}