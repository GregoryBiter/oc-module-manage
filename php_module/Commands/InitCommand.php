<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда инициализации модуля.
 */
class InitCommand extends Command {
    protected $description = 'Инициализация списка файлов модуля и запись в JSON';

    public function handle(Input $input, Output $output) {
        $args = $input->getArguments();
        init($args);
    }
}
