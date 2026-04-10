<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда установки модуля.
 */
class InstallCommand extends Command {
    protected $description = 'Копирование файлов модуля в папку OpenCart';

    public function handle(Input $input, Output $output) {
        $opencart_paths = defined('OPENCART_PATHS') ? OPENCART_PATHS : [OPENCART_DIR];
        
        foreach ($opencart_paths as $target_path) {
            if (!is_dir($target_path)) {
                $output->error("Директория OpenCart не существует: {$target_path}");
                continue;
            }
            
            $output->info(">>> Установка в: {$target_path}");
            $this->installToPath($target_path, $output);
        }
        
        $output->info("\nУстановка завершена.");
    }

    private function installToPath($target_path, Output $output) {
        $existing_files = load_files_list();
        $new_files = [];
        $all_current_files = find_all_files(MODULE_DIR, MODULE_DIR);

        foreach ($all_current_files as $relative_path) {
            $src_path = MODULE_DIR . '/' . $relative_path;
            $dest_path = $target_path . '/' . $relative_path;
            
            // Создание директории если не существует
            $dest_dir = dirname($dest_path);
            if (!is_dir($dest_dir)) {
                mkdir($dest_dir, 0777, true);
            }
            
            copy($src_path, $dest_path);
            $output->writeln("  Копирование: {$relative_path}");
            
            if (!in_array($relative_path, $existing_files)) {
                $new_files[] = $relative_path;
            }
        }

        // Обновляем локальный список файлов
        if (!empty($new_files)) {
            $updated_files = array_unique(array_merge($existing_files, $new_files));
            save_files_list($updated_files);
        }

        // Вызов OpenCart Integration (OCMOD) - функции из functions.php
        handle_ocmod($target_path);

        // Запись в базу (gdt_modules) - функции из functions.php
        sync_with_db($target_path, $all_current_files);
    }
}
