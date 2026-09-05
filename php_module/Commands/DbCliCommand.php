<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда интерактивной консоли MySQL (db:cli / db).
 */
class DbCliCommand extends Command {
    protected $name = 'db:cli';
    protected $description = 'Открыть интерактивный MySQL терминал к базе OpenCart';

    protected function configure() {
        $this->setName('db:cli')
             ->setAliases(['db'])
             ->setDescription('Запустить интерактивную сессию MySQL к базе связанного OpenCart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        $config = $this->getService('config');
        $database = $this->getService('database');

        $paths = $config->findOpenCartPaths();
        if (empty($paths)) {
            $io->error('Связанная директория OpenCart не найдена! Используйте: ocm link <путь>');
            return self::FAILURE;
        }

        $targetPath = $paths[0];

        try {
            $creds = $database->getCredentials($targetPath);
            if (!$creds) {
                $io->error("Не удалось прочитать параметры БД из {$targetPath}/config.php");
                return self::FAILURE;
            }

            $io->text("Подключение к базе данных <info>{$creds['database']}</info> на <info>{$creds['hostname']}:{$creds['port']}</info>...");

            $exitCode = $database->launchTerminal($targetPath);
            return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $io->note('Подсказка: если клиент mysql не установлен в системе, вы можете выполнять запросы через команду: ocm db:query "<SQL>"');
            return self::FAILURE;
        }
    }
}
