<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;

/**
 * Команда сборки модуля.
 */
class BuildCommand extends Command {
    protected $description = 'Сборка модуля на основе файла .build-module';

    public function handle(Input $input, Output $output) {
        $archive = $input->hasOption('a') || $input->hasOption('archive');
        build([], $archive);
    }
}
