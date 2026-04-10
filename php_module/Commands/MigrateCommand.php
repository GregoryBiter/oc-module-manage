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
        $config = $this->app->getService('config');

        if ($config->migrateOldFormat()) {
            $output->info("Выполнена миграция данных в новый формат:");
            $output->writeln("- Список файлов перемещен в .ocm_files.json");
            $output->writeln("- Метаданные модуля остались в opencart-module.json");
        } else {
            $output->comment("Миграция не требуется или файл opencart-module.json не содержит старых данных.");
        }
    }

}
