<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда импорта дампа базы данных (db:import).
 */
class DbImportCommand extends Command {
    protected $name = 'db:import';
    protected $description = 'Импортировать SQL-файл в базу данных OpenCart';

    protected function configure() {
        $this->setName('db:import')
             ->setDescription('Импортировать файл дампа (.sql или .sql.gz) в базу данных OpenCart')
             ->addArgument('file', InputArgument::REQUIRED, 'Путь к файлу .sql или .sql.gz')
             ->addOption('force', 'f', InputOption::VALUE_NONE, 'Выполнить без подтверждения');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Импорт базы данных OpenCart');

        $config = $this->getService('config');
        $database = $this->getService('database');

        $paths = $config->findOpenCartPaths();
        if (empty($paths)) {
            $io->error('Связанная директория OpenCart не найдена! Используйте: ocm link <путь>');
            return self::FAILURE;
        }

        $targetPath = $paths[0];
        $creds = $database->getCredentials($targetPath);
        if (!$creds) {
            $io->error("Не удалось прочитать параметры БД из {$targetPath}/config.php");
            return self::FAILURE;
        }

        $file = $input->getArgument('file');
        if (strpos($file, '/') !== 0) {
            $file = getcwd() . '/' . $file;
        }

        if (!file_exists($file)) {
            $io->error("Файл не найден: {$file}");
            return self::FAILURE;
        }

        $force = $input->getOption('force');
        if (!$force) {
            $io->warning("ВНИМАНИЕ! Импорт файла может перезаписать или удалить существующие данные в базе '{$creds['database']}'.");
            if (!$io->confirm("Вы уверены, что хотите импортировать '{$file}' в базу '{$creds['database']}'?", false)) {
                $io->note('Импорт отменен пользователем.');
                return self::SUCCESS;
            }
        }

        $io->text("Импорт <info>{$file}</info> в <info>{$creds['database']}</info>...");

        try {
            $startTime = microtime(true);
            $success = $database->import($targetPath, $file);

            if ($success) {
                $duration = round(microtime(true) - $startTime, 2);
                $io->success([
                    "Импорт успешно завершен за {$duration} сек!",
                    "База данных: {$creds['database']}"
                ]);
                return self::SUCCESS;
            } else {
                $io->error("Не удалось завершить импорт.");
                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $io->error('Ошибка при импорте: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
