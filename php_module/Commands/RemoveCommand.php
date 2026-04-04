<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда удаления модуля.
 */
class RemoveCommand extends Command {
    protected $description = 'Удаление файлов модуля из OpenCart';

    public function handle(Input $input, Output $output) {
        $args = $input->getArguments();
        remove($args);
    }
}
