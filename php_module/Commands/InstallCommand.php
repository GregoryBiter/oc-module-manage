<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда установки модуля.
 */
class InstallCommand extends Command {
    protected $description = 'Копирование файлов модуля в папку OpenCart';

    public function handle(Input $input, Output $output) {
        $args = $input->getArguments();
        install($args);
    }
}
