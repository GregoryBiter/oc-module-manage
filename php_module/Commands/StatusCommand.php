<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда отображения информации о статусе модуля.
 */
class StatusCommand extends Command {
    protected $name = 'status';
    protected $description = 'Показать статус текущего модуля и привязки к OpenCart';

    protected function configure() {
        $this->setName('status')
             ->setAliases(['info'])
             ->setDescription('Показать подробную информацию о модуле, файлах и привязке к OpenCart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Статус модуля');

        $config = $this->getService('config');
        $fileSystem = $this->getService('filesystem');

        $metadata = $config->loadModuleMetadata() ?: [];
        $currentDir = $config->getCurrentDir();
        $moduleDir = $config->getModuleDir();

        // 1. Метаданные
        $code = !empty($metadata['code']) ? $metadata['code'] : basename($currentDir);
        $name = !empty($metadata['module_name']) ? $metadata['module_name'] : (!empty($metadata['name']) ? $metadata['name'] : $code);
        $version = !empty($metadata['version']) ? $metadata['version'] : 'Не указана';
        $author = !empty($metadata['author']) ? $metadata['author'] : (!empty($metadata['creator_name']) ? $metadata['creator_name'] : 'Не указан');

        $io->section('Информация о модуле');
        $io->table(
            ['Параметр', 'Значение'],
            [
                ['Имя модуля', $name],
                ['Код (Code)', $code],
                ['Версия', $version],
                ['Автор', $author],
                ['Директория проекта', $currentDir],
                ['Файл конфигурации', file_exists($config->getJsonFile()) ? 'OK (' . basename($config->getJsonFile()) . ')' : 'Отсутствует'],
            ]
        );

        // 2. Файлы
        $uploadFilesCount = is_dir($moduleDir) ? count($fileSystem->findAllFiles($moduleDir, $moduleDir)) : 0;
        $trackedFilesCount = count($config->loadFilesList());
        $ocmodFileName = $config->getOcmodFileName();

        $io->section('Файловая структура');
        $io->table(
            ['Компонент', 'Статус / Количество'],
            [
                ['Папка upload/', is_dir($moduleDir) ? "Найдена ({$uploadFilesCount} файлов)" : 'Отсутствует'],
                ['Отслеживаемые файлы (.ocm/files.json)', "{$trackedFilesCount} файлов"],
                ['Модификатор OCMOD', $ocmodFileName ? "Найден ({$ocmodFileName})" : 'Отсутствует (install.xml / index.xml)'],
            ]
        );

        // 3. Целевой OpenCart
        $io->section('Связанные установки OpenCart');
        $paths = $config->findOpenCartPaths();

        if (empty($paths)) {
            $io->warning('Связанный OpenCart не найден! Используйте: ocm link <путь>');
        } else {
            $rows = [];
            foreach ($paths as $path) {
                $hasAdmin = file_exists($path . '/admin/config.php');
                $statusText = $hasAdmin ? 'Валидный OpenCart' : 'Каталог найден, но admin/config.php отсутствует';
                $rows[] = [$path, $statusText];
            }
            $io->table(['Путь к OpenCart', 'Статус'], $rows);
        }

        return self::SUCCESS;
    }
}
