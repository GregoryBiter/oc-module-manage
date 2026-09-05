<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда для просмотра доступных внешних скриптов.
 */
class ScriptListCommand extends Command {
    protected $name = 'script:list';
    protected $description = 'Список доступных внешних скриптов (scripts/)';

    protected function configure() {
        $this->setName('script:list')
             ->setAliases(['scripts'])
             ->setDescription('Показать все доступные дополнительные скрипты (lamp.sh и др.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Доступные внешние скрипты');

        /** @var \Ocm\Services\ScriptService $scriptService */
        $scriptService = $this->getService('script');
        if (!$scriptService) {
            $scriptService = new \Ocm\Services\ScriptService();
        }

        $dirs = $scriptService->getScriptDirectories();

        $io->section('Источники скриптов (в порядке приоритета):');
        $dirRows = [];
        $typeLabels = [
            'local' => '1. Локальные (проект ./.ocm/scripts/)',
            'user' => '2. Пользовательские (~/.config/ocm/scripts/)',
            'builtin' => '3. Встроенные в пакет (scripts/)'
        ];

        foreach ($dirs as $type => $dir) {
            $dirRows[] = [$typeLabels[$type] ?? $type, $dir];
        }
        $io->table(['Тип источника', 'Путь'], $dirRows);

        $scripts = $scriptService->getAvailableScripts();
        if (empty($scripts)) {
            $io->warning('Скрипты не найдены.');
            return self::SUCCESS;
        }

        $io->section('Найденные скрипты:');
        $scriptRows = [];
        foreach ($scripts as $name => $info) {
            $scriptRows[] = [
                $info['name'] . ' (' . $info['filename'] . ')',
                $info['type'],
                $info['description'],
                $info['path']
            ];
        }

        $io->table(['Скрипт', 'Источник', 'Описание', 'Расположение'], $scriptRows);
        $io->text([
            'Запустить скрипт можно напрямую командой:',
            '  <info>ocm <имя_скрипта></info>  (например: <info>ocm lamp</info>)',
            'или через runner:',
            '  <info>ocm script:run <имя> [аргументы...]</info>'
        ]);

        return self::SUCCESS;
    }
}
