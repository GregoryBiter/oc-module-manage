<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда установки файлов модуля в OpenCart (module:install / install).
 */
class InstallCommand extends Command {
    protected $name = 'module:install';
    protected $description = 'Копирование файлов модуля в установку OpenCart';

    protected function configure() {
        $this->setName('module:install')
             ->setAliases(['install'])
             ->setDescription('Копирование файлов модуля и регистрация модификаторов в OpenCart')
             ->addOption('no-db', null, InputOption::VALUE_NONE, 'Пропустить синхронизацию с БД OpenCart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Установка модуля в OpenCart');

        $config = $this->getService('config');
        $module = $this->getService('module');
        $fileSystem = $this->getService('filesystem');
        $skipDb = $input->getOption('no-db');

        $metadata = $config->loadModuleMetadata();
        $errors = [];
        if (!$config->validateMetadata($metadata, $errors)) {
            foreach ($errors as $error) {
                $io->error($error);
            }
            return self::FAILURE;
        }

        $opencartPaths = $config->findOpenCartPaths();
        if (empty($opencartPaths)) {
            $io->error([
                'Директория OpenCart не найдена!',
                'Укажите путь через: ocm link <путь_к_opencart>'
            ]);
            return self::FAILURE;
        }

        $moduleDir = $config->getModuleDir();
        if (!is_dir($moduleDir)) {
            $io->error("Директория 'upload' не найдена в текущем модуле: {$moduleDir}");
            return self::FAILURE;
        }

        $allCurrentFiles = $fileSystem->findAllFiles($moduleDir, $moduleDir);
        $existingFiles = $config->loadFilesList();
        $newFiles = [];

        foreach ($opencartPaths as $targetPath) {
            if (!is_dir($targetPath)) {
                $io->warning("Каталог OpenCart не существует: {$targetPath}");
                continue;
            }

            $io->section("Установка в OpenCart: {$targetPath}");
            $copiedCount = 0;

            foreach ($allCurrentFiles as $relativePath) {
                $srcPath = $moduleDir . '/' . $relativePath;
                $destPath = $targetPath . '/' . $relativePath;

                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0777, true);
                }

                if (copy($srcPath, $destPath)) {
                    $copiedCount++;
                }

                if (!in_array($relativePath, $existingFiles)) {
                    $newFiles[] = $relativePath;
                }
            }

            $io->text("  Скопировано файлов: <info>{$copiedCount}</info>");

            if (!$skipDb) {
                // Обработка модификатора (install.xml / index.xml)
                $ocmodFile = $config->getOcmodFilePath();
                if ($ocmodFile) {
                    $modName = basename($ocmodFile);
                    try {
                        $module->handleOcmod($targetPath);
                        $io->text("  Модификатор <info>{$modName}</info> записан в БД и скомпилирован.");
                    } catch (\Throwable $e) {
                        $io->warning("Предупреждение при установке {$modName}: " . $e->getMessage());
                    }
                } else {
                    $io->text("  Модификатор OCMOD не обнаружен (install.xml или index.xml отсутствует).");
                }

                // Запись в базу (ocm_*)
                try {
                    $module->syncWithDb($targetPath, $allCurrentFiles);
                    $io->text("  Записи модуля в таблицах ocm_* обновлены.");
                } catch (\Throwable $e) {
                    $io->warning("Предупреждение при синхронизации с БД: " . $e->getMessage());
                }
            } else {
                $io->note("Синхронизация с БД пропущена (флаг --no-db).");
            }
        }

        // Обновляем список отслеживаемых файлов
        if (!empty($newFiles)) {
            $updatedFiles = array_unique(array_merge($existingFiles, $newFiles));
            sort($updatedFiles);
            $config->saveFilesList($updatedFiles);
        }

        $io->success('Установка модуля успешно завершена!');
        return self::SUCCESS;
    }

    public function handle(Input $input, Output $output) {
        $config = $this->app->getService('config');
        $module = $this->app->getService('module');
        $fileSystem = $this->app->getService('filesystem');

        $installXmlPath = $this->app->getService('opencart')->getInstallXmlPath();
        if (!is_file($installXmlPath)) {
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

        $opencartPaths = defined('OPENCART_PATHS') ? OPENCART_PATHS : $config->findOpenCartPaths();
        if (empty($opencartPaths)) {
            $output->error("Директория OpenCart не найдена.");
            return;
        }

        foreach ($opencartPaths as $targetPath) {
            if (!is_dir($targetPath)) {
                $output->error("Директория OpenCart не существует: {$targetPath}");
                continue;
            }

            $output->info(">>> Установка в: {$targetPath}");
            $this->installToPath($targetPath, $output);
        }

        $output->info("\nУстановка завершена.");
    }

    private function installToPath($targetPath, Output $output) {
        $config = $this->app->getService('config');
        $fileSystem = $this->app->getService('filesystem');
        $module = $this->app->getService('module');

        $existingFiles = $config->loadFilesList();
        $newFiles = [];
        $moduleDir = $config->getModuleDir();
        $allCurrentFiles = $fileSystem->findAllFiles($moduleDir, $moduleDir);

        foreach ($allCurrentFiles as $relativePath) {
            $srcPath = $moduleDir . '/' . $relativePath;
            $destPath = $targetPath . '/' . $relativePath;

            $destDir = dirname($destPath);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }

            copy($srcPath, $destPath);
            $output->writeln("  Копирование: {$relativePath}");

            if (!in_array($relativePath, $existingFiles)) {
                $newFiles[] = $relativePath;
            }
        }

        if (!empty($newFiles)) {
            $updatedFiles = array_unique(array_merge($existingFiles, $newFiles));
            $config->saveFilesList($updatedFiles);
        }

        try {
            $module->handleOcmod($targetPath);
            $module->syncWithDb($targetPath, $allCurrentFiles);
        } catch (\Throwable $e) {
            $output->warning("Предупреждение при работе с БД: " . $e->getMessage());
        }
    }
}
