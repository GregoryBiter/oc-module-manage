<?php

namespace Ocm\Base;

/**
 * Класс для работы с аргументами и опциями командной строки.
 */
class Input {
    protected $arguments = [];
    protected $options = [];
    protected $full_argv = [];

    /**
     * Конструктор.
     */
    public function __construct($argv) {
        $this->full_argv = $argv;
        $this->parse();
    }

    /**
     * Пропарсить массив argv в аргументы и опции.
     */
    protected function parse() {
        // Мы предполагаем, что argv[0] это путь к скрипту, argv[1] это команда.
        // Поэтому начинаем с argv[2].
        for ($i = 2; $i < count($this->full_argv); $i++) {
            $arg = $this->full_argv[$i];
            if (strpos($arg, '--') === 0) {
                // Это опция. Может быть с параметром --option=val или просто флаг --option.
                $parts = explode('=', substr($arg, 2), 2);
                $name = $parts[0];
                $value = isset($parts[1]) ? $parts[1] : true;
                $this->options[$name] = $value;
            } elseif (strpos($arg, '-') === 0 && strlen($arg) > 1) {
                // Это короткая опция.
                $this->options[substr($arg, 1)] = true;
            } else {
                // Это аргумент.
                $this->arguments[] = $arg;
            }
        }
    }

    /**
     * Получить аргумент по индексу.
     */
    public function getArgument($index, $default = null) {
        return isset($this->arguments[$index]) ? $this->arguments[$index] : $default;
    }

    /**
     * Получить все аргументы.
     */
    public function getArguments() {
        return $this->arguments;
    }

    /**
     * Получить опцию по имени.
     */
    public function getOption($name, $default = null) {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    /**
     * Проверить существование опции.
     */
    public function hasOption($name) {
        return isset($this->options[$name]);
    }
}
