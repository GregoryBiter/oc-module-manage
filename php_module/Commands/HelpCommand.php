<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда вывода справки.
 */
class HelpCommand extends Command {
    protected $description = 'Вывод справки по доступным командам';

    public function handle(Input $input, Output $output) {
        $this->application->showHelp($output);
    }
}
