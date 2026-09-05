<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда вывода информации о базе данных OpenCart.
 */
class DbInfoCommand extends Command {
    protected $name = 'db:info';
    protected $description = 'Показать информацию о подключении к базе данных OpenCart';

    protected function configure() {
        $this->setName('db:info')
             ->setDescription('Проверить соединение и вывести информацию о базе данных OpenCart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Информация о базе данных');

        $config = $this->getService('config');
        $database = $this->getService('database');

        $paths = $config->findOpenCartPaths();
        if (empty($paths)) {
            $io->error('Связанная директория OpenCart не найдена! Используйте: ocm link <путь>');
            return self::FAILURE;
        }

        $targetPath = $paths[0];

        try {
            $info = $database->getInfo($targetPath);
            if (!$info) {
                $io->error("Не удалось прочитать параметры БД из {$targetPath}/config.php");
                return self::FAILURE;
            }

            $io->section("Подключение к БД ({$targetPath})");
            $io->table(
                ['Параметр', 'Значение'],
                [
                    ['Хост (Host)', $info['hostname'] . ':' . $info['port']],
                    ['База данных', $info['database']],
                    ['Пользователь', $info['username']],
                    ['Префикс таблиц', $info['prefix'] ?: '(без префикса)'],
                    ['Версия MySQL / MariaDB', $info['server_version']],
                    ['Всего таблиц', $info['table_count']],
                    ['Размер данных', $info['size_mb'] . ' MB'],
                ]
            );

            $io->success('Соединение с базой данных успешно установлено!');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Ошибка подключения к базе данных: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
