<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда режима разработки.
 */
class DevCommand extends Command {
    protected $description = 'Режим наблюдения за изменениями (development mode)';

    public function handle(Input $input, Output $output) {
        $args = $input->getArguments();
        dev($args);
    }
}
