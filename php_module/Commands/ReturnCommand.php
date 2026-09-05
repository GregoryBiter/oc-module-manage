<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда возврата файлов из OpenCart в папку модуля (module:pull / return).
 */
class ReturnCommand extends Command {
    protected $name = 'module:pull';
    protected $description = 'Возврат файлов из OpenCart в папку модуля (upload/)';

    protected function configure() {
        $this->setName('module:pull')
             ->setAliases(['return'])
             ->setDescription('Синхронизация файлов из OpenCart обратно в upload/ текущего модуля')
             ->addArgument('pattern', InputArgument::OPTIONAL, 'Шаблон файлов для возврата (например: *.php или catalog/**)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Возврат файлов из OpenCart в модуль');

        $config = $this->getService('config');
        $fileSystem = $this->getService('filesystem');

        $paths = $config->findOpenCartPaths();
        if (empty($paths)) {
            $io->error('Директория OpenCart не найдена!');
            return self::FAILURE;
        }

        $opencartDir = $paths[0];
        $files = $config->loadFilesList();

        if (empty($files)) {
            $io->error('Список отслеживаемых файлов пуст. Сначала выполните ocm install.');
            return self::FAILURE;
        }

        $pattern = $input->getArgument('pattern');
        $filesToReturn = [];

        if (!empty($pattern)) {
            foreach ($files as $file) {
                if ($config->matchWildcardPattern($pattern, $file)) {
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
            $io->warning('Нет файлов, соответствующих критериям для возврата.');
            return self::SUCCESS;
        }

        $moduleDir = $config->getModuleDir();
        if (!is_dir($moduleDir)) {
            mkdir($moduleDir, 0777, true);
        }

        $copiedCount = 0;
        foreach ($filesToReturn as $file) {
            $srcPath = $opencartDir . '/' . $file;
            $destPath = $moduleDir . '/' . $file;

            if (!file_exists($srcPath)) {
                $io->text("  [ПРОПУЩЕН] Не найден в OpenCart: {$file}");
                continue;
            }

            $destDir = dirname($destPath);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }

            if (copy($srcPath, $destPath)) {
                $io->text("  [PULL] Возвращен: <info>{$file}</info>");
                $copiedCount++;
            }
        }

        $io->success("Возврат файлов завершен. Обновлено файлов: {$copiedCount}");
        return self::SUCCESS;
    }

    public function handle(Input $input, Output $output) {
        $config = $this->app->getService('config');
        $fileSystem = $this->app->getService('filesystem');

        $paths = $config->findOpenCartPaths();
        if (empty($paths)) {
            $output->error("Путь к OpenCart не найден.");
            return;
        }
        $opencartDir = $paths[0];

        $files = $config->loadFilesList();
        if (empty($files)) {
            $output->error("Список файлов пуст.");
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
