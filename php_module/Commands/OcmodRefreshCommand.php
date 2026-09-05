<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда отдельного обновления модификаторов OpenCart (OCMOD).
 */
class OcmodRefreshCommand extends Command {
    protected $name = 'ocmod:refresh';
    protected $description = 'Обновить OCMOD модификаторы в связанном OpenCart';

    protected function configure() {
        $this->setName('ocmod:refresh')
             ->setDescription('Очистить кэш модификаторов и запустить admin refresh в OpenCart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Обновление модификаторов OCMOD');

        $config = $this->getService('config');
        $module = $this->getService('module');
        $openCart = $this->getService('opencart');

        $paths = $config->findOpenCartPaths();
        if (empty($paths)) {
            $io->error([
                'Связанная директория OpenCart не найдена!',
                'Используйте команду: ocm link <путь_к_opencart>'
            ]);
            return self::FAILURE;
        }

        foreach ($paths as $targetPath) {
            $io->section("Обработка OpenCart: {$targetPath}");

            // Если в текущем каталоге есть install.xml, синхронизируем его в БД модификаций
            $xmlFile = $config->getCurrentDir() . '/install.xml';
            if (file_exists($xmlFile)) {
                $io->text("Обновление модификатора из <info>install.xml</info> в БД...");
                $dbSuccess = $module->handleOcmod($targetPath);
                if (!$dbSuccess) {
                    $io->warning("Не удалось записать модификатор в базу данных OpenCart (проверьте настройки БД).");
                }
            }

            // Вызываем очистку и рефреш
            $io->text("Сброс кэша модификаций и перекомпиляция...");
            $openCart->refreshModifications($targetPath);
            $io->success("Модификаторы успешно обновлены для {$targetPath}!");
        }

        return self::SUCCESS;
    }
}
