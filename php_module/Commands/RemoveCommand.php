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
        $config = $this->app->getService('config');
        $opencart_paths = defined('OPENCART_PATHS') ? OPENCART_PATHS : [$config->findOpenCartPaths()[0]];
    
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
        $config = $this->app->getService('config');
        $module = $this->app->getService('module');
        $openCart = $this->app->getService('opencart');
        $fileSystem = $this->app->getService('filesystem');

        $identity = $module->resolveIdentity($target_path);
        $module_code = $identity['code'] ?: basename(getcwd());

        $files = $config->loadFilesList();
        if (empty($files)) {
            $output->comment("Нет записей о скопированных файлах в .ocm_files.json");
        } else {
            foreach ($files as $relative_path) {
                if ($fileSystem->removeFile($relative_path)) {
                    $output->writeln("  Удалено: {$relative_path}");
                }
            }
        }
    
        // Удаляем сам модуль из БД
        $db = $openCart->getOpenCartDbForPath($target_path);
        if ($db) {
            $code = $module_code;
            
            // Удаляем OCMOD
            $query = $db->query("SELECT * FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $db->escape($code) . "'");
            if ($query->num_rows) {
                $db->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $db->escape($code) . "'");
                $output->info("  Удален OCMOD-модификатор из БД.");
            }
    
            $openCart->removeModuleFromDb($target_path, $code);
            $output->info("  Удалены записи модуля из таблиц ocm_*.");
        }
    
        $openCart->refreshModifications($target_path);
    }

}
