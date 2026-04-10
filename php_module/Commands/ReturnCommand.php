<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда возврата файлов.
 */
class ReturnCommand extends Command {
    protected $description = 'Возврат файлов из OpenCart в папку модуля';

    public function handle(Input $input, Output $output) {
        $config = $this->app->getService('config');
        $fileSystem = $this->app->getService('filesystem');
        $openCart = $this->app->getService('opencart');

        $opencartDir = $config->findOpenCartPaths()[0];
        if (!$opencartDir) {
            $output->error("Путь к OpenCart не найден.");
            return;
        }

        $files = $config->loadFilesList();
        if (empty($files)) {
            $output->error("Список файлов пуст в .ocm_files.json.");
            return;
        }

        $args = $input->getArguments();
        $filesToReturn = [];

        if (!empty($args)) {
            foreach ($args as $pattern) {
                $matched = false;
                foreach ($files as $file) {
                    if ($config->matchWildcardPattern($pattern, $file)) {
                        if (strpos($file, '*') !== false) {
                            $realFiles = $fileSystem->resolveWildcardPattern($file, $opencartDir);
                            if (!empty($realFiles)) {
                                $filesToReturn = array_merge($filesToReturn, $realFiles);
                                $matched = true;
                            }
                        } else {
                            $filesToReturn[] = $file;
                            $matched = true;
                        }
                    }
                }
                if (!$matched) {
                    $output->warning("Шаблон '{$pattern}' не соответствует ни одному файлу.");
                }
            }
        } else {
            foreach ($files as $file) {
                if (strpos($file, '*') !== false) {
                    $realFiles = $fileSystem->resolveWildcardPattern($file, $opencartDir);
                    if (!empty($realFiles)) {
                        $filesToReturn = array_merge($filesToReturn, $realFiles);
                    }
                } else {
                    $filesToReturn[] = $file;
                }
            }
        }

        $filesToReturn = array_unique($filesToReturn);
        if (empty($filesToReturn)) {
            $output->error("Нет файлов для возврата.");
            return;
        }

        $output->info("Возврат файлов из OpenCart в модуль...");
        $copiedCount = 0;
        
        if (!is_dir($config->getModuleDir())) mkdir($config->getModuleDir(), 0777, true);

        foreach ($filesToReturn as $file) {
            $srcPath = $opencartDir . '/' . $file;
            $destPath = $config->getModuleDir() . '/' . $file;

            
            if (!file_exists($srcPath)) {
                $output->writeln("  Пропущен (не найден): {$file}");
                continue;
            }
            
            if (!is_dir(dirname($destPath))) mkdir(dirname($destPath), 0777, true);
            
            if (copy($srcPath, $destPath)) {
                $output->writeln("  Возвращен: {$file}");
                $copiedCount++;
            }
        }

        $output->success("Операция завершена. Возвращено файлов: {$copiedCount}");
    }

}
