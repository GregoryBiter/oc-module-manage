<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда миграции данных.
 */
class MigrateCommand extends Command {
    protected $description = 'Миграция данных из старого формата в новый';

    public function handle(Input $input, Output $output) {
        $args = $input->getArguments();
        migrate($args);
    }
}
