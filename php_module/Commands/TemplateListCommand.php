<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда для просмотра доступных шаблонов модулей.
 */
class TemplateListCommand extends Command {
    protected $name = 'template:list';
    protected $description = 'Список доступных шаблонов модулей OCM';

    protected function configure() {
        $this->setName('template:list')
             ->setDescription('Показать все доступные шаблоны модулей (локальные, пользовательские и встроенные)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Доступные шаблоны');

        $templateService = $this->getService('template');
        $dirs = $templateService->getTemplateDirectories();

        $io->section('Источники шаблонов (в порядке приоритета):');
        $dirRows = [];
        $typeLabels = [
            'local' => '1. Локальные (проект)',
            'user' => '2. Пользовательские (глобальные)',
            'builtin' => '3. Встроенные в пакет'
        ];

        foreach ($dirs as $type => $dir) {
            $dirRows[] = [$typeLabels[$type] ?? $type, $dir];
        }
        $io->table(['Тип источника', 'Путь'], $dirRows);

        $templates = $templateService->getAvailableTemplates();
        if (empty($templates)) {
            $io->warning('Шаблоны не найдены.');
            return self::SUCCESS;
        }

        $io->section('Найденные шаблоны:');
        $templateRows = [];
        foreach ($templates as $name => $info) {
            $templateRows[] = [$name, $info['type'], $info['path']];
        }

        $io->table(['Название шаблона', 'Источник', 'Расположение'], $templateRows);
        $io->text('Создать модуль на основе шаблона: <info>ocm make:module <имя> --template=<шаблон></info>');

        return self::SUCCESS;
    }
}
