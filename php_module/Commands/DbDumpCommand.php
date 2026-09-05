<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда дампа базы данных (db:dump / db:export).
 */
class DbDumpCommand extends Command {
    protected $name = 'db:dump';
    protected $description = 'Создать дамп базы данных OpenCart в SQL файл';

    protected function configure() {
        $this->setName('db:dump')
             ->setAliases(['db:export'])
             ->setDescription('Экспорт базы данных OpenCart (или отдельных таблиц) в SQL-файл')
             ->addArgument('file', InputArgument::OPTIONAL, 'Путь к выходному файлу .sql или .sql.gz')
             ->addOption('gzip', 'z', InputOption::VALUE_NONE, 'Сжать дамп в архив GZIP (.gz)')
             ->addOption('tables', 't', InputOption::VALUE_OPTIONAL, 'Список таблиц через запятую для выборочного дампа')
             ->addOption('prefix-only', null, InputOption::VALUE_NONE, 'Экспортировать только таблицы с префиксом OpenCart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Экспорт базы данных OpenCart');

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

        $isGzip = $input->getOption('gzip');
        $tablesOption = $input->getOption('tables');
        $prefixOnly = $input->getOption('prefix-only');

        $tables = [];
        if ($tablesOption) {
            $tables = array_filter(array_map('trim', explode(',', $tablesOption)));
        } elseif ($prefixOnly && !empty($creds['prefix'])) {
            $tableRows = $database->getTables($targetPath, $creds['prefix']);
            $tables = array_column($tableRows, 'table_name');
        }

        // Выходной файл
        $file = $input->getArgument('file');
        if (!$file) {
            $ext = $isGzip ? '.sql.gz' : '.sql';
            $file = getcwd() . '/' . $creds['database'] . '_' . date('Y-m-d_H-i-s') . $ext;
        } else {
            if ($isGzip && substr($file, -3) !== '.gz') {
                $file .= '.gz';
            }
            if (strpos($file, '/') !== 0) {
                $file = getcwd() . '/' . $file;
            }
        }

        $tableNotice = !empty($tables) ? ' (' . count($tables) . ' таблиц)' : ' (вся база данных)';
        $io->text("Экспорт базы <info>{$creds['database']}</info>{$tableNotice} в файл <info>{$file}</info>...");

        try {
            $startTime = microtime(true);
            $success = $database->dump($targetPath, $file, [
                'tables' => $tables,
                'gzip' => $isGzip || substr($file, -3) === '.gz'
            ]);

            if ($success && file_exists($file)) {
                $duration = round(microtime(true) - $startTime, 2);
                $sizeMb = round(filesize($file) / 1024 / 1024, 2);

                $io->success([
                    "Дамп успешно создан за {$duration} сек!",
                    "Файл: " . basename($file) . " ({$sizeMb} MB)",
                    "Полный путь: {$file}"
                ]);
                return self::SUCCESS;
            } else {
                $io->error("Не удалось создать дамп базы данных.");
                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $io->error('Ошибка при создании дампа: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
