<?php

namespace Ocm\Base;

/**
 * Основное приложение консоли (Kernel).
 */
class Application {
    protected $commands = [];
    protected $services = [];
    protected $name = 'OCM Manager';
    protected $version = '1.2.0';

    public function __construct() {
        $this->bootstrapServices();
    }

    /**
     * Инициализация базовых сервисов.
     */
    protected function bootstrapServices() {
        $fileSystem = new \Ocm\Services\FileSystemService();
        $config = new \Ocm\Services\ConfigService();
        $openCart = new \Ocm\Services\OpenCartService();
        $module = new \Ocm\Services\ModuleService($fileSystem, $config, $openCart);

        $this->services['filesystem'] = $fileSystem;
        $this->services['config'] = $config;
        $this->services['opencart'] = $openCart;
        $this->services['module'] = $module;
    }

    /**
     * Получить сервис по ключу.
     */
    public function getService($key) {
        return isset($this->services[$key]) ? $this->services[$key] : null;
    }

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
