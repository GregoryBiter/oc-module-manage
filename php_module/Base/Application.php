<?php

namespace Ocm\Base;

/**
 * Основное приложение консоли (Kernel).
 */
class Application {
    protected $commands = [];
    protected $name = 'OCM Manager';
    protected $version = '1.1.0';

    /**
     * Зарегистрировать команду.
     */
    public function add(Command $command) {
        $this->commands[$command->getName()] = $command;
        $command->setApplication($this);
    }

    /**
     * Запустить приложение.
     */
    public function run($argv) {
        $input = new Input($argv);
        $output = new Output();

        $command_name = isset($argv[1]) ? $argv[1] : 'help';

        if (isset($this->commands[$command_name])) {
            $command = $this->commands[$command_name];
            try {
                $command->handle($input, $output);
            } catch (\Exception $e) {
                $output->error($e->getMessage());
            }
        } else {
            // Если команда не найдена, пробуем запустить скрипт (предыдущий функционал)
            if ($command_name !== 'help') {
                $this->runExternalScript($command_name, $output);
            } else {
                $this->showHelp($output);
            }
        }
    }

    /**
     * Запуск внешнего скрипта.
     */
    protected function runExternalScript($script_name, $output) {
        if (function_exists('run_script')) {
            if (!run_script($script_name)) {
                $output->error("Команда или скрипт '{$script_name}' не найдены.");
                $this->showHelp($output);
            }
        } else {
            $output->error("Команда '{$script_name}' не найдена.");
            $this->showHelp($output);
        }
    }

    /**
     * Вывод общей справки.
     */
    public function showHelp($output) {
        $output->alert("==================================================");
        $output->alert("  {$this->name} - v{$this->version}");
        $output->alert("==================================================");
        $output->writeln("Использование: ocm <команда> [аргументы] [опции]");
        $output->writeln();
        $output->comment("Доступные команды:");

        foreach ($this->commands as $name => $command) {
            $output->writeln("  " . str_pad($name, 15) . " " . $command->getDescription());
        }

        // Вывод скриптов, если функция доступна
        if (defined('SCRIPT_DIR')) {
            $scripts_dir = SCRIPT_DIR . '/scripts/';
            if (is_dir($scripts_dir)) {
                $scripts = array_diff(scandir($scripts_dir), array('.', '..'));
                if (!empty($scripts)) {
                    $output->writeln();
                    $output->comment("Кастомные скрипты:");
                    foreach ($scripts as $script) {
                        $output->writeln("  " . str_pad($script, 15) . " Запуск кастомного скрипта");
                    }
                }
            }
        }
        $output->writeln();
    }

    public function getCommands() {
        return $this->commands;
    }
}
