<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда возврата файлов.
 */
class ReturnCommand extends Command {
    protected $description = 'Возврат файлов из OpenCart в папку модуля';

    public function handle(Input $input, Output $output) {
        $args = $input->getArguments();
        return_files($args);
    }
}
