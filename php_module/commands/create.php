<?php
/**
 * Создание нового модуля по выбранному шаблону.
 */

function create($args = []) {
    $templates = [];
    if (is_dir(TEMPLATES_DIR)) {
        $items = scandir(TEMPLATES_DIR);
        foreach ($items as $item) {
            if ($item != '.' && $item != '..' && is_dir(TEMPLATES_DIR . '/' . $item)) {
                $templates[] = $item;
            }
        }
    }
    
    if (empty($templates)) {
        echo "Шаблоны не найдены.\n";
        return;
    }

    echo "Доступные шаблоны:\n";
    foreach ($templates as $i => $template) {
        echo ($i + 1) . ". {$template}\n";
    }

    echo "Введите номер шаблона: ";
    $template_number = (int)trim(fgets(STDIN)) - 1;
    
    if ($template_number < 0 || $template_number >= count($templates)) {
        echo "Неверный номер шаблона.\n";
        return;
    }

    $template_dir = TEMPLATES_DIR . '/' . $templates[$template_number];
    echo "Введите имя нового модуля (snake_case): ";
    $module_name = trim(fgets(STDIN));
    $camel_case_name = to_camel_case($module_name);
    $camel_case_lower_name = to_camel_case_lower($module_name);

    $new_module_dir = CURRENT_DIR . '/' . $module_name;

    if (is_dir($new_module_dir)) {
        echo "Модуль {$module_name} уже существует.\n";
        return;
    }

    // Копирование шаблона
    copy_dir_recursively($template_dir, $new_module_dir, $module_name, $camel_case_name, $camel_case_lower_name);
    
    echo "Модуль {$module_name} создан на основе шаблона {$templates[$template_number]}.\n";
}
