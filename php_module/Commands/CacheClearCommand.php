<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда очистки системного кэша OpenCart.
 */
class CacheClearCommand extends Command {
    protected $name = 'cache:clear';
    protected $description = 'Очистить кэш OpenCart (system/storage/cache)';

    protected function configure() {
        $this->setName('cache:clear')
             ->setAliases(['cc'])
             ->setDescription('Очистить системный кэш OpenCart (файлы в system/storage/cache)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Очистка системного кэша OpenCart');

        $config = $this->getService('config');
        $fileSystem = $this->getService('filesystem');

        $paths = $config->findOpenCartPaths();
        if (empty($paths)) {
            $io->error([
                'Связанная директория OpenCart не найдена!',
                'Используйте команду: ocm link <путь_к_opencart>'
            ]);
            return self::FAILURE;
        }

        foreach ($paths as $targetPath) {
            $cacheDir = rtrim($targetPath, '/') . '/system/storage/cache';
            if (!is_dir($cacheDir)) {
                $io->warning("Каталог кэша не найден: {$cacheDir}");
                continue;
            }

            $io->text("Очистка: {$cacheDir}...");
            $items = scandir($cacheDir);
            $deletedCount = 0;

            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || $item === 'index.html' || $item === '.gitignore') {
                    continue;
                }
                $path = $cacheDir . '/' . $item;
                if (is_dir($path)) {
                    $fileSystem->cleanDirectory($path);
                    @rmdir($path);
                    $deletedCount++;
                } elseif (is_file($path)) {
                    @unlink($path);
                    $deletedCount++;
                }
            }

            $io->success("Кэш очищен для {$targetPath} (удалено записей: {$deletedCount})");
        }

        return self::SUCCESS;
    }
}
