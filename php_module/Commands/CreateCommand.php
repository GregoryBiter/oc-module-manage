<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда создания модуля.
 */
class CreateCommand extends Command {
    protected $description = 'Создание нового модуля по шаблону';

    public function handle(Input $input, Output $output) {
        $args = $input->getArguments();
        create($args);
    }
}
