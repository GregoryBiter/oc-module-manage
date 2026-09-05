<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда привязки текущего модуля к установке OpenCart.
 */
class LinkCommand extends Command {
    protected $name = 'link';
    protected $description = 'Привязать текущий модуль к директории OpenCart';

    protected function configure() {
        $this->setName('link')
             ->setDescription('Привязать текущий модуль к директории OpenCart')
             ->addArgument('path', InputArgument::OPTIONAL, 'Путь к корневой папке OpenCart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Привязка к OpenCart');

        $config = $this->getService('config');
        $targetPath = $input->getArgument('path');

        if (!$targetPath) {
            $existingPaths = $config->findOpenCartPaths();
            $default = !empty($existingPaths) ? $existingPaths[0] : '';
            $targetPath = $io->ask('Укажите абсолютный или относительный путь к каталогу OpenCart', $default);
        }

        if (empty($targetPath)) {
            $io->error('Путь к OpenCart не может быть пустым.');
            return self::FAILURE;
        }

        $realPath = realpath($targetPath);
        if (!$realPath || !is_dir($realPath)) {
            $io->error("Директория не существует: {$targetPath}");
            return self::FAILURE;
        }

        if (!file_exists($realPath . '/config.php') || !file_exists($realPath . '/admin/config.php')) {
            $io->warning("Внимание: в каталоге '{$realPath}' не найдены config.php или admin/config.php. Возможно, это не корень OpenCart.");
            if (!$io->confirm('Всё равно привязать этот каталог?', false)) {
                return self::FAILURE;
            }
        }

        $config->saveOpenCartTarget($realPath);

        $io->success([
            "Модуль успешно привязан к OpenCart!",
            "Целевой путь: {$realPath}",
            "Конфигурация сохранена в .ocm/target и .opencart"
        ]);

        return self::SUCCESS;
    }
}
