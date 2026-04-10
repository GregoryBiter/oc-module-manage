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
        $config = $this->app->getService('config');
        $module = $this->app->getService('module');
        $fileSystem = $this->app->getService('filesystem');

        $install_xml_path = $this->app->getService('opencart')->getInstallXmlPath();
        if (!is_file($install_xml_path)) {
            $output->comment("Подсказка: install.xml не найден, пропуск модификаторов.");
        }

        $metadata = $config->loadModuleMetadata();
        $errors = [];
        if (!$config->validateMetadata($metadata, $errors)) {
            foreach ($errors as $error) {
                $output->error($error);
            }
            return;
        }

        $opencart_paths = defined('OPENCART_PATHS') ? OPENCART_PATHS : [$this->app->getService('config')->findOpenCartPaths()[0]];
        
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
        $config = $this->app->getService('config');
        $fileSystem = $this->app->getService('filesystem');
        $module = $this->app->getService('module');

        $existing_files = $config->loadFilesList();
        $new_files = [];
        $moduleDir = $config->getModuleDir();
        $all_current_files = $fileSystem->findAllFiles($moduleDir, $moduleDir);

        foreach ($all_current_files as $relative_path) {
            $src_path = $moduleDir . '/' . $relative_path;
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
            $config->saveFilesList($updated_files);
        }

        // Вызов OpenCart Integration (OCMOD) - install.xml
        if (!$module->handleOcmod($target_path)) {
            // handleOcmod возвращает true если файла нет (пропуск) или false только при реальной ошибке БД
            return;
        }

        // Запись в базу (ocm_*)
        $module->syncWithDb($target_path, $all_current_files);
    }

}
