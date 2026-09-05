<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда установки AI Agent скилов и инструкций (agent:install).
 */
class AgentInstallCommand extends Command {
    protected $name = 'agent:install';
    protected $description = 'Установка AI Agent скилов и правил (opencart_ai_agent) в OpenCart';

    protected function configure() {
        $this->setName('agent:install')
             ->setAliases(['agent', 'skills:install', 'skills'])
             ->setDescription('Установка AI Agent скилов и правил (opencart_ai_agent) в OpenCart')
             ->addArgument('path', InputArgument::OPTIONAL, 'Путь к директории OpenCart')
             ->addOption('repo', null, InputOption::VALUE_REQUIRED, 'Репозиторий со скилами', \Ocm\Services\AgentSkillService::DEFAULT_REPO)
             ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Ветка репозитория', 'main')
             ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Локальный путь к исходникам opencart_ai_agent')
             ->addOption('symlink', 's', InputOption::VALUE_NONE, 'Использовать символические ссылки вместо копирования (при наличии --source)')
             ->addOption('global', 'g', InputOption::VALUE_NONE, 'Установить скилы глобально в систему (~/.agents/skills)')
             ->addOption('adapters', null, InputOption::VALUE_REQUIRED, 'Адаптеры для других AI инструментов (cursor, claude, all)', 'default')
             ->addOption('force', 'f', InputOption::VALUE_NONE, 'Перезаписать существующие файлы без подтверждения');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Установка AI Agent Skills (OpenCart AI Agent)');

        /** @var \Ocm\Services\AgentSkillService $agentService */
        $agentService = $this->getService('agent');
        if (!$agentService) {
            $agentService = new \Ocm\Services\AgentSkillService($this->getService('filesystem'));
        }

        $configService = $this->getService('config');

        $isGlobal = (bool)$input->getOption('global');
        $targetPath = null;

        if (!$isGlobal) {
            $pathArg = $input->getArgument('path');
            $targetPath = $agentService->resolveTargetPath($pathArg, $configService);

            if (!$targetPath || !is_dir($targetPath)) {
                if ($input->isInteractive()) {
                    $targetPath = $io->ask('Укажите путь к корневой директории OpenCart');
                }
            }

            if (!$targetPath || !is_dir($targetPath)) {
                $io->error("Директория OpenCart не найдена. Укажите путь аргументом: ocm agent:install /path/to/opencart или используйте --global");
                return self::FAILURE;
            }

            $io->text("Целевая директория OpenCart: <info>{$targetPath}</info>");
        } else {
            $io->text("Режим установки: <info>Глобально в систему (~/.agents/skills, ~/.config/ocm/skills)</info>");
        }

        $options = [
            'repo' => $input->getOption('repo'),
            'branch' => $input->getOption('branch'),
            'source' => $input->getOption('source'),
            'symlink' => (bool)$input->getOption('symlink'),
            'global' => $isGlobal,
            'adapters' => $input->getOption('adapters'),
            'force' => (bool)$input->getOption('force')
        ];

        $sourceDesc = !empty($options['source']) ? $options['source'] : $options['repo'];
        $io->text("Загрузка скилов из: <comment>{$sourceDesc}</comment> (ветка: {$options['branch']})...");

        try {
            $result = $agentService->install($targetPath, $options);

            if ($isGlobal) {
                $io->success("Скилы успешно установлены глобально!");
                foreach ($result['global'] as $dir => $skills) {
                    $io->text("Каталог <info>{$dir}</info>:");
                    foreach ($skills as $s) {
                        $io->text("  - {$s}");
                    }
                }
                return self::SUCCESS;
            }

            $io->section("Результаты установки в OpenCart:");
            $items = [];
            if (!empty($result['files'])) {
                $items[] = 'Файлы инструкций: ' . implode(', ', $result['files']);
            }
            if (!empty($result['skills'])) {
                $items[] = 'Установленные скилы: ' . implode(', ', $result['skills']);
            }
            if (!empty($result['rules'])) {
                $items[] = 'Установленные правила: ' . implode(', ', $result['rules']);
            }
            $io->listing($items);

            if (!empty($result['adapters'])) {
                $io->text("Настроены адаптеры: <info>" . implode(', ', $result['adapters']) . "</info>");
            }

            $io->success("AI Agent скилы успешно установлены в {$targetPath}!");
            $io->note("Теперь AI-ассистенты (Antigravity, Copilot, Cursor, Claude) автоматически видят правила и скилы OpenCart в вашем проекте.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error("Ошибка при установке скилов: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
