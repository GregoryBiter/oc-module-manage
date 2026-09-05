<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда просмотра установленных AI Agent скилов (agent:list).
 */
class AgentListCommand extends Command {
    protected $name = 'agent:list';
    protected $description = 'Список установленных AI Agent скилов и правил';

    protected function configure() {
        $this->setName('agent:list')
             ->setAliases(['skills:list'])
             ->setDescription('Список установленных AI Agent скилов и правил')
             ->addArgument('path', InputArgument::OPTIONAL, 'Путь к директории OpenCart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Установленные AI Agent Skills');

        /** @var \Ocm\Services\AgentSkillService $agentService */
        $agentService = $this->getService('agent');
        if (!$agentService) {
            $agentService = new \Ocm\Services\AgentSkillService($this->getService('filesystem'));
        }

        $configService = $this->getService('config');
        $pathArg = $input->getArgument('path');
        $targetPath = $agentService->resolveTargetPath($pathArg, $configService);

        if (!$targetPath || !is_dir($targetPath)) {
            $io->error("Директория OpenCart не найдена. Укажите путь: ocm agent:list /path/to/opencart");
            return self::FAILURE;
        }

        $info = $agentService->getInstalledInfo($targetPath);

        $io->section("Статус конфигурации агентов в {$targetPath}");
        $io->table(
            ['Компонент', 'Статус'],
            [
                ['Главные правила (AGENTS.md)', $info['has_agents_md'] ? '✅ Установлен' : '❌ Отсутствует'],
                ['GitHub Copilot (.github/copilot-instructions.md)', $info['has_copilot_instructions'] ? '✅ Настроен' : '❌ Отсутствует'],
                ['Cursor IDE (.cursorrules / .cursor/rules)', $info['has_cursor_rules'] ? '✅ Настроен' : '❌ Отсутствует'],
            ]
        );

        $io->section("Доступные скилы (.agents/skills)");
        if (empty($info['skills'])) {
            $io->warning("Скилы не установлены. Выполните: ocm agent:install {$targetPath}");
        } else {
            $rows = [];
            foreach ($info['skills'] as $skill) {
                $rows[] = [$skill['name'], $skill['description'], $skill['path']];
            }
            $io->table(['Скил', 'Описание', 'Путь'], $rows);
        }

        $io->section("Правила поведения (.agents/rules)");
        if (empty($info['rules'])) {
            $io->text("Отдельные файлы правил не найдены.");
        } else {
            $rows = [];
            foreach ($info['rules'] as $rule) {
                $rows[] = [$rule['file'], $rule['path']];
            }
            $io->table(['Файл правила', 'Путь'], $rows);
        }

        return self::SUCCESS;
    }
}
