<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда режима разработки.
 */
class DevCommand extends Command {
    protected $description = 'Режим наблюдения за изменениями (development mode)';

    public function handle(Input $input, Output $output) {
        $output->info("Выполняется первичная установка...");
        
        // Вызов установки
        $installCmd = new InstallCommand();
        $installCmd->handle($input, $output);
        
        $opencart_paths = defined('OPENCART_PATHS') ? OPENCART_PATHS : [OPENCART_DIR];
        
        $output->comment("\nЗапущен режим наблюдения. Нажмите Ctrl+C для выхода.");
        $output->writeln("Отслеживаемые пути:\n - " . implode("\n - ", $opencart_paths));
        
        // Начальное состояние файлов в upload/
        $files_map = [];
        $all_files = find_all_files(MODULE_DIR, MODULE_DIR);
        foreach ($all_files as $file) {
            $files_map[$file] = filemtime(MODULE_DIR . '/' . $file);
        }
        
        // Начальное состояние index.xml
        $ocmod_file = CURRENT_DIR . '/index.xml';
        $ocmod_mtime = file_exists($ocmod_file) ? filemtime($ocmod_file) : 0;
        
        // Основной цикл наблюдения
        while (true) {
            clearstatcache();
            
            // 1. Проверка index.xml
            if (file_exists($ocmod_file)) {
                $current_ocmod_mtime = filemtime($ocmod_file);
                if ($current_ocmod_mtime != $ocmod_mtime) {
                    $output->info("\n[CHANGE] Обнаружены изменения в index.xml. Обновление модификаторов...");
                    foreach ($opencart_paths as $target_path) {
                        handle_ocmod($target_path);
                    }
                    $ocmod_mtime = $current_ocmod_mtime;
                }
            }
            
            // 2. Проверка файлов в upload/
            $current_files = find_all_files(MODULE_DIR, MODULE_DIR);
            $current_files_map = [];
            foreach ($current_files as $file) {
                $current_files_map[$file] = filemtime(MODULE_DIR . '/' . $file);
            }
            
            // Ищем изменения или новые файлы
            foreach ($current_files_map as $file => $mtime) {
                if (!isset($files_map[$file]) || $files_map[$file] != $mtime) {
                    $output->info("\n[CHANGE] Файл изменен или добавлен: {$file}");
                    foreach ($opencart_paths as $target_path) {
                        $this->syncFileToPath($file, $target_path, $output);
                    }
                    $files_map[$file] = $mtime;
                }
            }
            
            // Ищем удаленные файлы
            foreach ($files_map as $file => $mtime) {
                if (!isset($current_files_map[$file])) {
                    $output->comment("\n[DELETE] Файл удален: {$file}");
                    foreach ($opencart_paths as $target_path) {
                        $this->removeFileFromPath($file, $target_path, $output);
                    }
                    unset($files_map[$file]);
                }
            }
            
            sleep(1);
        }
    }

    private function syncFileToPath($relative_path, $target_path, Output $output) {
        if (!is_dir($target_path)) return;
        
        $src_path = MODULE_DIR . '/' . $relative_path;
        $dest_path = $target_path . '/' . $relative_path;
        
        $dest_dir = dirname($dest_path);
        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0777, true);
        }
        
        if (copy($src_path, $dest_path)) {
            $output->writeln("  Синхронизировано в {$target_path}: {$relative_path}");
            
            // Обновляем локальный список если нужно
            $files = load_files_list();
            if (!in_array($relative_path, $files)) {
                $files[] = $relative_path;
                sort($files);
                save_files_list($files);
            }
        }
    }
    
    private function removeFileFromPath($relative_path, $target_path, Output $output) {
        $dest_path = $target_path . '/' . $relative_path;
        
        if (file_exists($dest_path)) {
            unlink($dest_path);
            $output->writeln("  Удалено из {$target_path}: {$relative_path}");
        }
        
        // Чистка пустых папок
        $parent_dir = dirname($dest_path);
        while ($parent_dir != $target_path && is_dir($parent_dir)) {
            if (count(scandir($parent_dir)) <= 2) {
                rmdir($parent_dir);
                $parent_dir = dirname($parent_dir);
            } else {
                break;
            }
        }
    
        // Обновляем локальный список если нужно
        $files = load_files_list();
        $key = array_search($relative_path, $files);
        if ($key !== false) {
            unset($files[$key]);
            save_files_list(array_values($files));
        }
    }
}
