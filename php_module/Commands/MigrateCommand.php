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
        if (!file_exists(JSON_FILE)) {
            $output->comment("Файл opencart-module.json не найден. Миграция не требуется.");
            return;
        }
        
        $data = json_decode(file_get_contents(JSON_FILE), true);
        if (!$data || !isset($data['files'])) {
            $output->comment("Нет старого поля files в метаданных. Миграция не требуется.");
            return;
        }
        
        // Сохраняем файлы в новый формат
        $files = $data['files'];
        save_files_list($files);
        
        // Удаляем поле files из метаданных и сохраняем
        unset($data['files']);
        save_module_metadata($data);
        
        $output->info("Выполнена миграция данных в новый формат:");
        $output->writeln("- Список файлов перемещен в .ocm_files.json");
        $output->writeln("- Метаданные модуля остались в opencart-module.json");
    }
}
