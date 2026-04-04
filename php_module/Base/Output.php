<?php

namespace Ocm\Base;

/**
 * Класс для работы с выводом в консоль (цвета, форматирование).
 */
class Output {
    // Цвета
    const RED = "\033[0;31m";
    const GREEN = "\033[0;32m";
    const YELLOW = "\033[1;33m";
    const BLUE = "\033[0;34m";
    const BOLD = "\033[1m";
    const NC = "\033[0m"; // No Color

    /**
     * Вывести сообщение (обычное).
     */
    public function writeln($message = "") {
        echo $message . "\n";
    }

    /**
     * Информационное сообщение (зеленое).
     */
    public function info($message) {
        $this->writeln(self::GREEN . $message . self::NC);
    }

    /**
     * Сообщение об ошибке (красное).
     */
    public function error($message) {
        $this->writeln(self::RED . "[ERROR] " . $message . self::NC);
    }

    /**
     * Комментарий или предупреждение (желтое).
     */
    public function comment($message) {
        $this->writeln(self::YELLOW . $message . self::NC);
    }

    /**
     * Громкое сообщение (синее или жирное).
     */
    public function alert($message) {
        $this->writeln(self::BLUE . self::BOLD . $message . self::NC);
    }
}
