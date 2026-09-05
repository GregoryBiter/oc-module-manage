<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда для запуска внешнего скрипта.
 */
class ScriptRunCommand extends Command {
    protected $name = 'script:run';
    protected $description = 'Запуск дополнительного внешнего скрипта (lamp, test и др.)';

    protected function configure() {
        $this->setName('script:run')
             ->setAliases(['run', 'script', 'exec'])
             ->setDescription('Запуск внешнего скрипта из папки scripts/ (например: ocm script:run lamp)')
             ->addArgument('script', InputArgument::REQUIRED, 'Имя или путь к скрипту (например: lamp или lamp.sh)')
             ->addArgument('arguments', InputArgument::IS_ARRAY, 'Аргументы, передаваемые скрипту');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        /** @var \Ocm\Services\ScriptService $scriptService */
        $scriptService = $this->getService('script');
        if (!$scriptService) {
            $scriptService = new \Ocm\Services\ScriptService();
        }

        $scriptName = $input->getArgument('script');
        $args = (array)$input->getArgument('arguments');

        $scriptPath = $scriptService->resolveScriptPath($scriptName);
        if (!$scriptPath) {
            $io->error("Скрипт '{$scriptName}' не найден. Список доступных скриптов: ocm script:list");
            return self::FAILURE;
        }

        $io->text("Выполнение скрипта: <info>{$scriptPath}</info>...");
        $exitCode = $scriptService->execute($scriptPath, $args);

        return $exitCode;
    }
}
