<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда инициализации модуля.
 */
class InitCommand extends Command {
    protected $name = 'init';
    protected $description = 'Инициализация списка файлов модуля и запись в JSON';

    protected function configure() {
        $this->setName('init')
             ->setDescription('Инициализация метаданных модуля и списка файлов (.ocm/files.json)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Инициализация модуля');

        $config = $this->getService('config');
        $fileSystem = $this->getService('filesystem');

        if ($config->migrateOldFormat()) {
            $io->note("Выполнена миграция данных в формат .ocm/");
        }

        $metadata = $config->loadModuleMetadata();
        if ($metadata) {
            $io->text("Файл opencart-module.json уже существует. Обновление списка файлов...");
        } else {
            $defaultName = basename(getcwd());
            $moduleName = $io->ask('Имя модуля', $defaultName);
            $defaultCode = strtolower(str_replace(' ', '_', $moduleName));
            $code = $io->ask('Код модуля (code)', $defaultCode);
            $version = $io->ask('Версия', '1.0.0');
            $author = $io->ask('Автор / Организация', 'ocm');

            $metadata = [
                'module_name' => $moduleName,
                'code' => $code,
                'version' => $version,
                'author' => $author
            ];

            $config->saveModuleMetadata($metadata);
            $io->success("Метаданные модуля сохранены в opencart-module.json");
        }

        $moduleDir = $config->getModuleDir();
        if (!is_dir($moduleDir)) {
            $io->text("Директория upload/ не найдена. Создание...");
            mkdir($moduleDir, 0777, true);
        }

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
        foreach ($currentFiles as $file) {
            if (in_array($file, $regularFiles)) continue;

            $patternMatched = false;
            foreach ($wildcardPatterns as $pattern) {
                if ($config->matchWildcardPattern($pattern, $file)) {
                    $patternMatched = true;
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

        $io->success("Индексация файлов завершена. Всего файлов: " . count($updatedFiles));
        if ($newFiles) $io->text(" - Добавлено файлов: " . count($newFiles));
        if ($deletedFiles) $io->text(" - Удалено файлов: " . count($deletedFiles));

        return self::SUCCESS;
    }

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
