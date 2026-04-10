<?php

namespace Ocm\Base;

/**
 * Базовый класс для команд Artisan-типа.
 */
abstract class Command {
    protected $name;
    protected $description = '';
    protected $app;

    /**
     * Конструктор.
     */
    public function __construct() {
        if (empty($this->name)) {
            $this->name = $this->extractNameFromClassName();
        }
    }

    /**
     * Основная логика выполнения команды.
     */
    abstract public function handle(Input $input, Output $output);

    /**
     * Получить имя команды.
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Получить описание команды.
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * Установить приложение.
     */
    public function setApplication($app) {
        $this->app = $app;
    }

    /**
     * Извлечь имя команды из имени класса, если оно не задано вручную.
     */
    protected function extractNameFromClassName() {
        $class = basename(str_replace('\\', '/', get_class($this)));
        $class = str_replace('Command', '', $class);
        return strtolower($class);
    }
}
