<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда выполнения SQL-запроса в базе данных OpenCart.
 */
class DbQueryCommand extends Command {
    protected $name = 'db:query';
    protected $description = 'Выполнить SQL-запрос к базе данных OpenCart';

    protected function configure() {
        $this->setName('db:query')
             ->setDescription('Выполнить произвольный SQL-запрос к БД OpenCart и вывести результат')
             ->addArgument('query', InputArgument::OPTIONAL, 'SQL-запрос для выполнения');
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
        $sql = $input->getArgument('query');

        if (!$sql) {
            $sql = $io->ask('Введите SQL-запрос для выполнения');
        }

        if (empty(trim($sql))) {
            $io->error('SQL-запрос не может быть пустым.');
            return self::FAILURE;
        }

        try {
            $startTime = microtime(true);
            $result = $database->query($targetPath, $sql);
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            if ($result['type'] === 'select') {
                if ($result['count'] === 0) {
                    $io->note("Запрос выполнен успешно за {$durationMs} ms. Записей не найдено (0 rows).");
                    return self::SUCCESS;
                }

                $io->text("Результат (<info>{$result['count']} строк</info>, выполнено за <info>{$durationMs} ms</info>):");

                // Для красивого отображения форматируем строки
                $rows = array_map(function($row) {
                    return array_map(function($val) {
                        if ($val === null) return '<fg=gray>NULL</>';
                        if (is_bool($val)) return $val ? 'true' : 'false';
                        if (strlen($val) > 80) return mb_substr($val, 0, 77) . '...';
                        return $val;
                    }, $row);
                }, $result['rows']);

                $io->table($result['columns'], $rows);
            } else {
                $io->success("Запрос выполнен успешно за {$durationMs} ms. Затронуто строк: {$result['affected']}");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Ошибка выполнения SQL: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
