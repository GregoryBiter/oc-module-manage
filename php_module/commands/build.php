<?php
/**
 * Сборка модуля на основе файла .build-module
 */
function build($args = [], $archive = false) {
    if (!file_exists(BUILD_FILE)) {
        echo "Файл .build-module не найден в текущей директории.\n";
        echo "Хотите создать пример файла .build-module? (y/n): ";
        $answer = trim(fgets(STDIN));
        
        if (strtolower($answer) === 'y' || strtolower($answer) === 'yes') {
            create_example_build_file();
            echo "Файл .build-module создан с примером. Отредактируйте его и запустите команду build снова.\n";
        } else {
            echo "Операция отменена.\n";
        }
        return;
    }
    
    // Создаем директорию сборки, если не существует
    $build_module_dir = dirname(BUILD_DIR);
    if (!is_dir($build_module_dir)) {
        mkdir($build_module_dir, 0777, true);
    }
    if (!is_dir(BUILD_DIR)) {
        mkdir(BUILD_DIR, 0777, true);
    } else {
        // Очищаем директорию сборки перед началом
        clean_directory(BUILD_DIR);
    }
    
    $patterns = file(BUILD_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $copied_files = [];
    
    foreach ($patterns as $pattern) {
        // Пропускаем комментарии
        if (strpos($pattern, '#') === 0) {
            continue;
        }
        
        $pattern = trim($pattern);
        if (empty($pattern)) continue;
        
        $matched_files = find_files_by_pattern($pattern);
        
        foreach ($matched_files as $file) {
            $dest_path = BUILD_DIR . '/' . $file;
            $dest_dir = dirname($dest_path);
            
            if (!is_dir($dest_dir)) {
                mkdir($dest_dir, 0777, true);
            }
            
            if (copy(CURRENT_DIR . '/' . $file, $dest_path)) {
                $copied_files[] = $file;
                echo "Копирование: {$file}\n";
            } else {
                echo "Ошибка при копировании: {$file}\n";
            }
        }
    }
    
    if (empty($copied_files)) {
        echo "Не найдено файлов для копирования на основе шаблонов в .build-module.\n";
        echo "Проверьте правильность шаблонов и наличие соответствующих файлов.\n";
    } else {
        echo "Сборка завершена. Скопировано файлов: " . count($copied_files) . "\n";
        echo "Файлы находятся в: " . BUILD_DIR . "\n";
        
        // Создаем архив, если указан флаг
        if ($archive) {
            if (check_zip_extension()) {
                $archive_path = create_module_archive();
                if ($archive_path) {
                    echo "Создан архив: {$archive_path}\n";
                }
            }
        }
    }
}

/**
 * Создает ZIP-архив с собранным модулем
 */
function create_module_archive() {
    $module_name = basename(CURRENT_DIR);
    $archive_path = dirname(BUILD_DIR) . "/{$module_name}.ocmod.zip";
    
    // Удаляем существующий архив если есть
    if (file_exists($archive_path)) {
        unlink($archive_path);
    }
    
    $zip = new ZipArchive();
    if ($zip->open($archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        // Добавляем содержимое директории upload
        $success = add_dir_to_zip($zip, BUILD_DIR, '');
        $zip->close();
        
        if ($success && file_exists($archive_path)) {
            return $archive_path;
        } else {
            echo "Ошибка при архивации файлов!\n";
            return false;
        }
    } else {
        echo "Ошибка при создании архива!\n";
        return false;
    }
}

/**
 * Рекурсивно добавляет директорию в ZIP-архив
 */
function add_dir_to_zip($zip, $dir, $zip_dir) {
    if (!is_dir($dir)) {
        echo "Ошибка: директория {$dir} не существует!\n";
        return false;
    }
    
    $files = scandir($dir);
    if ($files === false) {
        echo "Ошибка при сканировании директории {$dir}!\n";
        return false;
    }
    
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        
        $file_path = $dir . '/' . $file;
        $zip_path = $zip_dir . ($zip_dir ? '/' : '') . $file;
        
        if (is_dir($file_path)) {
            // Создаем директорию в архиве
            if ($zip->addEmptyDir($zip_path) === false) {
                echo "Ошибка при добавлении директории {$zip_path} в архив!\n";
                return false;
            }
            // Рекурсивно добавляем содержимое директории
            if (!add_dir_to_zip($zip, $file_path, $zip_path)) {
                return false;
            }
        } else if (is_file($file_path)) {
            // Добавляем файл в архив
            if ($zip->addFile($file_path, $zip_path) === false) {
                echo "Ошибка при добавлении файла {$file_path} в архив!\n";
                return false;
            }
        }
    }
    
    return true;
}

/**
 * Создание примера файла .build-module
 */
function create_example_build_file() {
    $example_content = <<<EOT
# Файл конфигурации сборки модуля OpenCart
# Каждая строка - это шаблон для поиска файлов
# Поддерживаемые шаблоны:
# * - любые файлы в текущем каталоге
# ** - все файлы рекурсивно во всех подкаталогах

# Контроллеры
admin/controller/extension/module/*.php
admin/controller/extension/payment/my_payment.php

# Модели
admin/model/**

# Языковые файлы
admin/language/ru-ru/extension/module/*.php
admin/language/en-gb/extension/module/*.php

# Шаблоны
admin/view/template/extension/module/*.twig
admin/view/template/extension/module/*.tpl

# Файлы каталога
catalog/controller/extension/module/my_module.php
catalog/model/extension/module/my_module.php
catalog/view/theme/*/template/extension/module/my_module.twig

# Статические файлы
admin/view/image/my_module/*.*
catalog/view/javascript/my_module.js
catalog/view/css/my_module.css
EOT;

    file_put_contents(BUILD_FILE, $example_content);
    echo "Создан пример файла .build-module в " . CURRENT_DIR . "\n";
}
