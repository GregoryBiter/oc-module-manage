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
        $script_path = $this->resolveExternalScriptPath($script_name);

        if ($script_path === null) {
            $output->error("Команда или скрипт '{$script_name}' не найдены.");
            $this->showHelp($output);
            return;
        }

        $exit_code = $this->executeExternalScript($script_path);

        if ($exit_code !== 0) {
            $output->error("Скрипт '{$script_name}' завершился с кодом {$exit_code}.");
        }
    }

    /**
     * Найти файл внешнего скрипта по имени команды.
     */
    protected function resolveExternalScriptPath($script_name) {
        if (!defined('SCRIPT_DIR')) {
            return null;
        }

        $scripts_dir = SCRIPT_DIR . '/scripts/';
        $candidates = [$scripts_dir . $script_name];

        if (pathinfo($script_name, PATHINFO_EXTENSION) === '') {
            $candidates[] = $scripts_dir . $script_name . '.php';
            $candidates[] = $scripts_dir . $script_name . '.sh';
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Запустить найденный внешний скрипт.
     */
    protected function executeExternalScript($script_path) {
        $extension = strtolower(pathinfo($script_path, PATHINFO_EXTENSION));

        if ($extension === 'php') {
            passthru('php ' . escapeshellarg($script_path), $exit_code);
            return $exit_code;
        }

        if ($extension === 'sh') {
            passthru('bash ' . escapeshellarg($script_path), $exit_code);
            return $exit_code;
        }

        passthru(escapeshellarg($script_path), $exit_code);
        return $exit_code;
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
