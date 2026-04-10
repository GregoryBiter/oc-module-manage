<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда удаления модуля.
 */
class RemoveCommand extends Command {
    protected $description = 'Удаление файлов модуля из OpenCart';

    public function handle(Input $input, Output $output) {
        $opencart_paths = defined('OPENCART_PATHS') ? OPENCART_PATHS : [OPENCART_DIR];
    
        foreach ($opencart_paths as $target_path) {
            if (!is_dir($target_path)) {
                $output->error("Директория OpenCart не существует: {$target_path}");
                continue;
            }
            
            $output->info(">>> Удаление из: {$target_path}");
            $this->removeFromPath($target_path, $output);
        }
        
        $output->info("\nУдаление завершено.");
    }
    
    private function removeFromPath($target_path, Output $output) {
        $identity = resolve_module_identity($target_path);
        $module_code = $identity['code'] ?: basename(CURRENT_DIR);

        $files = load_files_list();
        if (empty($files)) {
            $output->comment("Нет записей о скопированных файлах в .ocm_files.json");
        } else {
            foreach ($files as $relative_path) {
                $dest_path = $target_path . '/' . $relative_path;
                if (file_exists($dest_path) && !is_dir($dest_path)) {
                    unlink($dest_path);
                    $output->writeln("  Удалено: {$relative_path}");
                }
            }
        }
    
        // Удаляем сам модуль из БД
        $db = get_opencart_db_for_path($target_path);
        if ($db) {
            $code = $module_code;
            
            // Удаляем OCMOD
            $query = $db->query("SELECT * FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $db->escape($code) . "'");
            if ($query->num_rows) {
                $db->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $db->escape($code) . "'");
                $output->info("  Удален OCMOD-модификатор из БД.");
            }
    
            remove_module_from_db($target_path, $code);
            $output->info("  Удалены записи модуля из таблиц ocm_*.");
        }
    
        refresh_modifications($target_path);
    }
}
