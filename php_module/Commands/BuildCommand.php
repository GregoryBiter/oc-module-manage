<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда сборки модуля.
 */
class BuildCommand extends Command {
    protected $description = 'Сборка модуля на основе файла .build-module';

    public function handle(Input $input, Output $output) {
        $fileSystem = $this->app->getService('filesystem');
        $config = $this->app->getService('config');
        $archive = $input->hasOption('a') || $input->hasOption('archive');

        // Проверяем наличие файла opencart-module.json
        $metadata = $config->loadModuleMetadata();
        if ($metadata) {
            $output->info("Файл opencart-module.json найден. Выполняется сборка всей текущей папки в ZIP-архив...");
            $archivePath = $this->createFullFolderArchive($output);
            if ($archivePath) {
                $output->success("Создан архив: " . basename($archivePath));
            }
            return;
        }

        $buildFile = getcwd() . '/.build-module';
        if (!file_exists($buildFile)) {
            $output->error("Файл .build-module не найден.");
            if ($output->confirm("Хотите создать пример файла .build-module?")) {
                $this->createExampleBuildFile();
                $output->success("Файл .build-module создан. Отредактируйте его и запустите сборку снова.");
            }
            return;
        }
        
        // Подготовка директории сборки
        $buildDir = getcwd() . '/build-module/upload';
        if (!is_dir(dirname($buildDir))) mkdir(dirname($buildDir), 0777, true);
        if (!is_dir($buildDir)) {
            mkdir($buildDir, 0777, true);
        } else {
            $fileSystem->cleanDirectory($buildDir);
        }
        
        $patterns = file($buildFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $copiedCount = 0;
        
        foreach ($patterns as $pattern) {
            if (strpos(trim($pattern), '#') === 0 || empty(trim($pattern))) continue;
            
            $matchedFiles = $fileSystem->findFilesByPattern(trim($pattern));
            foreach ($matchedFiles as $file) {
                $destPath = $buildDir . '/' . $file;
                if (!is_dir(dirname($destPath))) mkdir(dirname($destPath), 0777, true);
                
                if (copy(getcwd() . '/' . $file, $destPath)) {
                    $copiedCount++;
                    $output->writeln("  Копирование: {$file}");
                }
            }
        }
        
        if ($copiedCount === 0) {
            $output->warning("Не найдено файлов для копирования.");
        } else {
            $output->success("Сборка завершена. Скопировано файлов: {$copiedCount}");
            
            if ($archive && $fileSystem->checkZipExtension()) {
                $archivePath = $this->createModuleArchive($output);
                if ($archivePath) {
                    $output->success("Создан архив: " . basename($archivePath));
                }
            }
        }
    }

    private function createFullFolderArchive(Output $output) {
        $config = $this->app->getService('config');
        $fileSystem = $this->app->getService('filesystem');
        
        $metadata = $config->loadModuleMetadata();
        $moduleCode = isset($metadata['code']) ? $metadata['code'] : basename(getcwd());
        $archivePath = getcwd() . "/{$moduleCode}.zip";

        if (file_exists($archivePath)) unlink($archivePath);

        $zip = new \ZipArchive();
        if ($zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            $fileSystem->addDirToZip($zip, getcwd(), '');
            $zip->close();
            return $archivePath;
        }
        return false;
    }

    private function createModuleArchive(Output $output) {
        $fileSystem = $this->app->getService('filesystem');
        $buildDir = getcwd() . '/build-module/upload';
        $moduleName = basename(getcwd());
        $archivePath = dirname($buildDir) . "/{$moduleName}.ocmod.zip";

        if (file_exists($archivePath)) unlink($archivePath);

        $zip = new \ZipArchive();
        if ($zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            $fileSystem->addDirToZip($zip, $buildDir, '');
            $zip->close();
            return $archivePath;
        }
        return false;
    }

    private function createExampleBuildFile() {
        $content = <<<EOT
# Файл конфигурации сборки модуля OpenCart
# Каждая строка - это шаблон для поиска файлов
# * - любые файлы в каталоге, ** - рекурсивно

admin/controller/extension/module/*.php
admin/model/extension/module/**
admin/language/ru-ru/extension/module/*.php
admin/view/template/extension/module/*.twig
catalog/controller/extension/module/*.php
EOT;
        file_put_contents(getcwd() . '/.build-module', $content);
    }

}
