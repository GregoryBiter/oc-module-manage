<?php

namespace Ocm\Base;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Базовый класс для команд OCM (Artisan-style).
 * Совместим с Symfony Console и поддерживает легаси-метод handle(Input, Output).
 */
abstract class Command extends SymfonyCommand {
    protected $name;
    protected $description = '';
    protected $app;

    public function __construct($name = null) {
        if (empty($this->name) && $name === null) {
            $this->name = $this->extractNameFromClassName();
        }
        parent::__construct($name ?: $this->name);

        if (!empty($this->description)) {
            $this->setDescription($this->description);
        }
    }

    /**
     * Установить приложение.
     */
    public function setApplication(?\Symfony\Component\Console\Application $application): void {
        $this->app = $application;
        parent::setApplication($application);
    }

    public function getApplication(): ?\Symfony\Component\Console\Application {
        $parentApp = parent::getApplication();
        if ($parentApp) {
            return $parentApp;
        }
        return ($this->app instanceof \Symfony\Component\Console\Application) ? $this->app : null;
    }

    public function getService($key) {
        $app = $this->getApplication();
        if ($app && method_exists($app, 'getService')) {
            return $app->getService($key);
        }
        if ($this->app && method_exists($this->app, 'getService')) {
            return $this->app->getService($key);
        }
        return null;
    }

    /**
     * Основная логика выполнения Symfony Console.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int {
        // Обертка для вызова устаревшего handle(), если он реализован в дочернем классе
        $legacyInput = new Input($_SERVER['argv'] ?? []);
        $legacyOutput = new Output();

        try {
            $this->handle($legacyInput, $legacyOutput);
            return SymfonyCommand::SUCCESS;
        } catch (\Throwable $e) {
            $io = new SymfonyStyle($input, $output);
            $io->error($e->getMessage());
            return SymfonyCommand::FAILURE;
        }
    }

    /**
     * Легаси-обработчик для обратной совместимости.
     */
    public function handle(Input $input, Output $output) {
        // Переопределяется в командах
    }

    /**
     * Извлечь имя команды из имени класса.
     */
    protected function extractNameFromClassName() {
        $class = basename(str_replace('\\', '/', get_class($this)));
        $class = str_replace('Command', '', $class);
        return strtolower($class);
    }
}
