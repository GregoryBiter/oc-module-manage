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
        $config = $this->app->getService('config');
        $fileSystem = $this->app->getService('filesystem');
        $templatesDir = defined('TEMPLATES_DIR') ? TEMPLATES_DIR : dirname(dirname(dirname(__FILE__))) . '/templates';

        $templates = [];
        if (is_dir($templatesDir)) {
            $items = scandir($templatesDir);
            foreach ($items as $item) {
                if ($item != '.' && $item != '..' && is_dir($templatesDir . '/' . $item)) {
                    $templates[] = $item;
                }
            }
        }
        
        if (empty($templates)) {
            $output->error("Шаблоны не найдены.");
            return;
        }

        $output->info("Доступные шаблоны:");
        foreach ($templates as $i => $template) {
            $output->writeln(($i + 1) . ". {$template}");
        }

        $selection = (int)$output->ask("Введите номер шаблона: ", 1) - 1;
        
        if ($selection < 0 || $selection >= count($templates)) {
            $output->error("Неверный номер шаблона.");
            return;
        }

        $templateDir = $templatesDir . '/' . $templates[$selection];
        $moduleName = $output->ask("Введите имя нового модуля (snake_case): ");
        if (empty($moduleName)) {
            $output->error("Имя модуля не может быть пустым.");
            return;
        }

        $camelCaseName = $config->toCamelCase($moduleName);
        $camelCaseLowerName = $config->toCamelCaseLower($moduleName);

        $newModuleDir = getcwd() . '/' . $moduleName;

        if (is_dir($newModuleDir)) {
            $output->error("Модуль {$moduleName} уже существует.");
            return;
        }

        // Копирование шаблона
        $placeholders = [
            '{{#ModuleName}}' => $camelCaseName,
            '{{#moduleName}}' => $camelCaseLowerName,
            '{{#module_name}}' => $moduleName
        ];

        $fileSystem->copyDirRecursively($templateDir, $newModuleDir, $placeholders);
        
        $output->success("Модуль {$moduleName} создан на основе шаблона {$templates[$selection]}.");
    }

}
