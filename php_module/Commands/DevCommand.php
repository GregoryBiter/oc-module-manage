<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда режима разработки с отслеживанием файлов (module:dev / dev / watch).
 */
class DevCommand extends Command {
    protected $name = 'module:dev';
    protected $description = 'Режим наблюдения за изменениями файлов и авто-синхронизация';

    protected function configure() {
        $this->setName('module:dev')
             ->setAliases(['dev', 'watch'])
             ->setDescription('Отслеживание изменений в upload/ и install.xml с мгновенной синхронизацией в OpenCart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Режим разработки (Watch Mode)');

        $config = $this->getService('config');
        $fileSystem = $this->getService('filesystem');
        $module = $this->getService('module');

        $opencartPaths = $config->findOpenCartPaths();
        if (empty($opencartPaths)) {
            $io->error([
                'Директория OpenCart не найдена!',
                'Укажите путь через: ocm link <путь_к_opencart>'
            ]);
            return self::FAILURE;
        }

        // Первичная установка
        $io->section('Выполняется первичная установка файлов...');
        $installCmd = new InstallCommand();
        $installCmd->setApplication($this->getApplication());
        $installCmd->execute($input, $output);

        $moduleDir = $config->getModuleDir();
        $ocmodFile = $config->getOcmodFilePath();
        $ocmodFileName = $ocmodFile ? basename($ocmodFile) : null;
        $ocmodMtime = ($ocmodFile && file_exists($ocmodFile)) ? filemtime($ocmodFile) : 0;

        $io->section('Режим наблюдения активирован (Ctrl+C для выхода)');
        $io->text([
            "Папка модуля: <info>{$moduleDir}</info>",
            "Модификатор OCMOD: " . ($ocmodFileName ? "<info>{$ocmodFileName}</info> (активен)" : "<comment>не найден (install.xml или index.xml)</comment>"),
            "Целевые установки OpenCart:",
            " - " . implode("\n - ", $opencartPaths)
        ]);

        // Начальное состояние файлов
        $filesMap = [];
        if (is_dir($moduleDir)) {
            $allFiles = $fileSystem->findAllFiles($moduleDir, $moduleDir);
            foreach ($allFiles as $file) {
                $filesMap[$file] = filemtime($moduleDir . '/' . $file);
            }
        }

        // Основной цикл слежения
        while (true) {
            clearstatcache();

            // 1. Проверка модификатора (install.xml / index.xml)
            $currentOcmodFile = $config->getOcmodFilePath();
            if ($currentOcmodFile && file_exists($currentOcmodFile)) {
                $currentOcmodMtime = filemtime($currentOcmodFile);
                if ($currentOcmodMtime !== $ocmodMtime || $currentOcmodFile !== $ocmodFile) {
                    $modName = basename($currentOcmodFile);
                    $io->text("\n<comment>[" . date('H:i:s') . "] Обнаружены изменения в {$modName}. Обновление модификатора в OpenCart...</comment>");
                    foreach ($opencartPaths as $targetPath) {
                        try {
                            $module->handleOcmod($targetPath);
                            $module->syncWithDb($targetPath, array_keys($filesMap));
                            $io->success("Модификатор {$modName} успешно обновлен в БД и скомпилирован для {$targetPath}!");
                        } catch (\Throwable $e) {
                            $io->warning("Ошибка обновления модификатора: " . $e->getMessage());
                        }
                    }
                    $ocmodFile = $currentOcmodFile;
                    $ocmodMtime = $currentOcmodMtime;
                }
            } elseif ($ocmodFile && !file_exists($ocmodFile)) {
                $io->text("\n<comment>[" . date('H:i:s') . "] Файл модификатора удален. Очистка из OpenCart...</comment>");
                foreach ($opencartPaths as $targetPath) {
                    try {
                        $identity = $module->resolveIdentity($targetPath);
                        \Ocm\Services\OpenCartService::removeModificationByCode($targetPath, $identity['code']);
                        \Ocm\Services\OpenCartService::refreshModifications($targetPath);
                    } catch (\Throwable $e) {}
                }
                $ocmodFile = null;
                $ocmodMtime = 0;
            }

            // 2. Проверка файлов в upload/
            if (is_dir($moduleDir)) {
                $currentFiles = $fileSystem->findAllFiles($moduleDir, $moduleDir);
                $currentFilesMap = [];
                foreach ($currentFiles as $file) {
                    $currentFilesMap[$file] = filemtime($moduleDir . '/' . $file);
                }

                // Измененные или добавленные файлы
                foreach ($currentFilesMap as $file => $mtime) {
                    if (!isset($filesMap[$file]) || $filesMap[$file] !== $mtime) {
                        $io->text("\n<info>[" . date('H:i:s') . "] Изменен или добавлен:</info> {$file}");
                        foreach ($opencartPaths as $targetPath) {
                            $src = $moduleDir . '/' . $file;
                            $dest = $targetPath . '/' . $file;
                            $destDir = dirname($dest);
                            if (!is_dir($destDir)) mkdir($destDir, 0777, true);
                            copy($src, $dest);
                            $io->text("  -> Синхронизировано в {$targetPath}");
                        }
                        $filesMap[$file] = $mtime;

                        // Обновляем список отслеживаемых
                        $tracked = $config->loadFilesList();
                        if (!in_array($file, $tracked)) {
                            $tracked[] = $file;
                            sort($tracked);
                            $config->saveFilesList($tracked);
                        }
                    }
                }

                // Удаленные файлы
                foreach ($filesMap as $file => $mtime) {
                    if (!isset($currentFilesMap[$file])) {
                        $io->text("\n<comment>[" . date('H:i:s') . "] Удален:</comment> {$file}");
                        foreach ($opencartPaths as $targetPath) {
                            $dest = $targetPath . '/' . $file;
                            if (file_exists($dest)) {
                                unlink($dest);
                                $io->text("  -> Удален из {$targetPath}");
                            }
                        }
                        unset($filesMap[$file]);

                        $tracked = $config->loadFilesList();
                        $key = array_search($file, $tracked);
                        if ($key !== false) {
                            unset($tracked[$key]);
                            $config->saveFilesList(array_values($tracked));
                        }
                    }
                }
            }

            sleep(1);
        }

        return self::SUCCESS;
    }

    public function handle(Input $input, Output $output) {
        $fileSystem = $this->app->getService('filesystem');
        $config = $this->app->getService('config');
        $module = $this->app->getService('module');

        $output->info("Выполняется первичная установка...");
        $installCmd = new InstallCommand();
        $installCmd->setApplication($this->app);
        $installCmd->handle($input, $output);

        $opencartPaths = defined('OPENCART_PATHS') ? OPENCART_PATHS : $config->findOpenCartPaths();
        if (empty($opencartPaths)) {
            $output->error("Директория OpenCart не найдена.");
            return;
        }

        $output->comment("\nЗапущен режим наблюдения. Нажмите Ctrl+C для выхода.");

        $moduleDir = $config->getModuleDir();
        $filesMap = [];
        if (is_dir($moduleDir)) {
            $allFiles = $fileSystem->findAllFiles($moduleDir, $moduleDir);
            foreach ($allFiles as $file) {
                $filesMap[$file] = filemtime($moduleDir . '/' . $file);
            }
        }

        $ocmodFile = getcwd() . '/install.xml';
        $ocmodMtime = file_exists($ocmodFile) ? filemtime($ocmodFile) : 0;

        while (true) {
            clearstatcache();
            if (file_exists($ocmodFile)) {
                $currentOcmodMtime = filemtime($ocmodFile);
                if ($currentOcmodMtime != $ocmodMtime) {
                    $output->info("\n[CHANGE] Обнаружены изменения в install.xml. Обновление модификаторов...");
                    foreach ($opencartPaths as $targetPath) {
                        try {
                            $module->handleOcmod($targetPath);
                            $module->syncWithDb($targetPath, array_keys($filesMap));
                        } catch (\Throwable $e) {}
                    }
                    $ocmodMtime = $currentOcmodMtime;
                }
            }

            if (is_dir($moduleDir)) {
                $currentFiles = $fileSystem->findAllFiles($moduleDir, $moduleDir);
                $currentFilesMap = [];
                foreach ($currentFiles as $file) {
                    $currentFilesMap[$file] = filemtime($moduleDir . '/' . $file);
                }

                foreach ($currentFilesMap as $file => $mtime) {
                    if (!isset($filesMap[$file]) || $filesMap[$file] != $mtime) {
                        $output->info("\n[CHANGE] Файл изменен или добавлен: {$file}");
                        foreach ($opencartPaths as $targetPath) {
                            $src = $moduleDir . '/' . $file;
                            $dest = $targetPath . '/' . $file;
                            $destDir = dirname($dest);
                            if (!is_dir($destDir)) mkdir($destDir, 0777, true);
                            copy($src, $dest);
                        }
                        $filesMap[$file] = $mtime;
                    }
                }

                foreach ($filesMap as $file => $mtime) {
                    if (!isset($currentFilesMap[$file])) {
                        $output->comment("\n[DELETE] Файл удален: {$file}");
                        foreach ($opencartPaths as $targetPath) {
                            $dest = $targetPath . '/' . $file;
                            if (file_exists($dest)) unlink($dest);
                        }
                        unset($filesMap[$file]);
                    }
                }
            }

            sleep(1);
        }
    }
}
