<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда инициализации модуля.
 */
class InitCommand extends Command {
    protected $description = 'Инициализация списка файлов модуля и запись в JSON';

    public function handle(Input $input, Output $output) {
        $config = $this->app->getService('config');
        $fileSystem = $this->app->getService('filesystem');

        // Миграция
        if ($config->migrateOldFormat()) {
            $output->info("Выполнена миграция данных в новый формат.");
        }

        // Метаданные
        $metadata = $config->loadModuleMetadata();
        if ($metadata) {
            $output->info("Файл opencart-module.json уже существует. Обновляем список файлов.");
        } else {
            $defaultName = basename(getcwd());
            
            $output->writeln("Введите данные модуля:");
            $moduleName = $output->ask("Имя модуля (по умолчанию: $defaultName): ", $defaultName);
            
            $defaultCode = strtolower(str_replace(' ', '_', $moduleName));
            $code = $output->ask("Code (по умолчанию: $defaultCode): ", $defaultCode);
            
            $version = $output->ask("Версия: ", "1.0.0");
            $creator = $output->ask("Создатель: ", "ocm");
            $email = $output->ask("Email создателя: ", "GBITStudio");

            $metadata = [
                'module_name' => $moduleName,
                'code' => $code,
                'version' => $version,
                'creator_name' => $creator,
                'creator_email' => $email
            ];
            
            $config->saveModuleMetadata($metadata);
            $output->success("Метаданные сохранены.");
        }

        // Директория
        $moduleDir = $config->getModuleDir();
        if (!is_dir($moduleDir)) {
            $output->info("Директория модуля не найдена. Создаем 'upload'...");
            mkdir($moduleDir, 0777, true);
        }

        // Обновление списка файлов
        $currentFiles = $fileSystem->findAllFiles($moduleDir, $moduleDir);

        $existingFiles = $config->loadFilesList();

        $wildcardPatterns = [];
        $regularFiles = [];
        foreach ($existingFiles as $file) {
            if (strpos($file, '*') !== false) {
                $wildcardPatterns[] = $file;
            } else {
                $regularFiles[] = $file;
            }
        }

        $newFiles = [];
        $matchedByPattern = [];
        foreach ($currentFiles as $file) {
            if (in_array($file, $regularFiles)) continue;

            $patternMatched = false;
            foreach ($wildcardPatterns as $pattern) {
                if ($config->matchWildcardPattern($pattern, $file)) {
                    $patternMatched = true;
                    $matchedByPattern[] = $file;
                    break;
                }
            }

            if (!$patternMatched) {
                $newFiles[] = $file;
            }
        }

        $deletedFiles = array_diff($regularFiles, $currentFiles);

        $updatedFiles = array_merge(
            array_diff($regularFiles, $deletedFiles),
            $newFiles,
            $wildcardPatterns
        );

        sort($updatedFiles);
        $config->saveFilesList($updatedFiles);

        $output->success("Обновление списка файлов завершено.");
        if ($newFiles) $output->info("- Добавлено файлов: " . count($newFiles));
        if ($deletedFiles) $output->info("- Удалено файлов: " . count($deletedFiles));
        $output->writeln("Всего файлов: " . count($updatedFiles));
    }

}
