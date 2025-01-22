<?php

namespace OcModule;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Finder\Finder;

class Opencart extends Factory
{

    private $json;
    public function __construct()
    {
        $this->json = new JsonTool();
    }

    public function console()
    {
        global $argv;
        if (php_sapi_name() == 'cli') {
            $command = $argv[1] ?? 'help';
            $script = null;
            if (isset($argv[2]) && $argv[2] == '--script') {
                $script = $argv[3] ?? null;
            }

            switch ($command) {
                case 'init':
                    $this->init();
                    break;
                case 'install':
                    $this->install();
                    break;
                case 'dev':
                    $this->dev();
                    break;
                case 'remove':
                    $this->remove();
                    break;
                case 'create':
                    $this->create();
                    break;
                case 'help':
                default:
                    $this->show_help();
                    break;
            }

            if ($script) {
                $this->run_script($script);
            }
        }
    }


    public function dev()
    {
        global $MODULE_DIR;
        $filesystem = new Filesystem();
        if (!$filesystem->exists($MODULE_DIR)) {
            echo "Директория $MODULE_DIR не существует.\n";
            return;
        }

        $data = $this->json->load();
        $opencart_dirs = get_opencart_dirs($data);
        $opencart_dir = $opencart_dirs[0];  // Используем первый путь, если не указан отдельный для dev

        $handler = new ChangeHandler($opencart_dir);
        $finder = new Finder();
        $finder->files()->in($MODULE_DIR);

        echo "Запущен режим наблюдения. Нажмите Ctrl+C для выхода.\n";
        while (true) {
            foreach ($finder as $file) {
                $file_path = $file->getRealPath();
                $handler->sync_file($file_path);
            }
            usleep(500000);  // Уменьшено время ожидания до 0.5 секунды
        }
    }

    public function run_script($script_path)
    {
        if (file_exists($script_path) && is_file($script_path)) {
            include $script_path;
        } else {
            echo "Скрипт $script_path не найден.\n";
        }
    }
    public function init()
    {
        global $MODULE_DIR;
        $filesystem = new Filesystem();
        if (!$filesystem->exists($MODULE_DIR)) {
            // Создать папку модуля
            mkdir($MODULE_DIR, 0777, true);
            // Создать папку upload
            // $path_upload = $MODULE_DIR . '/upload';
            // // if (!file_exists($path_upload)) {
            // //     mkdir($path_upload, 0777, true);
            // // }
        }

        $data = $this->json->load();
        if (!empty($data)) {
            echo "Файл opencart-module.json уже существует. Обновляем список файлов.\n";
        } else {
            while ($module_name == "") {
                $module_name = readline("Введите имя модуля: ");
            }
            $creator_name = readline("Введите имя создателя: ");
            $creator_email = readline("Введите email создателя: ");
            $opencart_dirs = explode(',', readline("Введите пути для копирования (через запятую): "));
            $data = [
                "module_name" => $module_name,
                "creator_name" => $creator_name,
                "creator_email" => $creator_email,

                "files" => []
            ];
            // //создать папку модуля
            // $path_module = $MODULE_DIR . '/' . $module_name;
            // if (!file_exists($path_module)) {
            //     mkdir($path_module, 0777, true);
            // }
        }



        $finder = new Finder();
        $files = [];
        foreach ($finder->files()->in($MODULE_DIR) as $file) {
            $files[] = str_replace($MODULE_DIR . '/', '', $file->getRealPath());
        }
        sort($files);
        $data['files'] = $files;
        $this->json->save($data);
        echo "Файлы инициализированы:\n" . implode("\n", $files) . "\n";
    }

    public function install()
    {
        global $MODULE_DIR;
        $filesystem = new Filesystem();
        if (!$filesystem->exists($MODULE_DIR)) {
            echo "Директория $MODULE_DIR не существует.\n";
            return;
        }

        $data = $this->json->load();
        $files = isset($data['files']) ? $data['files'] : [];
        $new_files = [];
        $opencart_dirs = get_opencart_dirs($data);
        $filesystem = new Filesystem();

        $finder = new Finder();
        foreach ($finder->files()->in($MODULE_DIR) as $file) {
            $relative_path = str_replace($MODULE_DIR . '/', '', $file->getRealPath());
            foreach ($opencart_dirs as $opencart_dir) {
                $dest_path = $opencart_dir . '/' . $relative_path;
                try {
                    $filesystem->mkdir(dirname($dest_path));
                    $filesystem->copy($file->getRealPath(), $dest_path, true);
                    echo "Копирование $relative_path -> " . str_replace($opencart_dir . '/', '', $dest_path) . "\n";
                    if (!in_array($relative_path, $files)) {
                        $new_files[] = $relative_path;
                    }
                } catch (IOExceptionInterface $exception) {
                    echo "Ошибка при копировании файла: " . $exception->getMessage() . "\n";
                }
            }
        }

        if (!empty($new_files)) {
            $data['files'] = array_merge($files, $new_files);
            $this->json->save($data);
            echo "Добавлены новые файлы: " . implode(", ", $new_files) . "\n";
        }
        echo "Установка завершена.\n";
    }

    public function remove()
    {
        global $MODULE_DIR;
        $filesystem = new Filesystem();
        if (!$filesystem->exists($MODULE_DIR)) {
            echo "Директория $MODULE_DIR не существует.\n";
            return;
        }

        $data = $this->json->load();
        $files = isset($data['files']) ? $data['files'] : [];
        $opencart_dirs = get_opencart_dirs($data);
        $filesystem = new Filesystem();

        foreach ($files as $relative_path) {
            foreach ($opencart_dirs as $opencart_dir) {
                $target_file = $opencart_dir . '/' . $relative_path;
                if (file_exists($target_file)) {
                    $filesystem->remove($target_file);
                    echo "Удалено: $relative_path\n";
                } else {
                    echo "Пропущено (файл не найден): $relative_path\n";
                }
                // Удаление пустых папок
                $parent_dir = dirname($target_file);
                while ($parent_dir != $opencart_dir) {
                    if (is_dir($parent_dir) && count(scandir($parent_dir)) == 2) {
                        $filesystem->remove($parent_dir);
                        echo "Удалена пустая папка: " . str_replace($opencart_dir . '/', '', $parent_dir) . "\n";
                    } else {
                        break;
                    }
                    $parent_dir = dirname($parent_dir);
                }
            }
        }
        echo "Удаление завершено.\n";
    }

    public function create()
    {
        global $TEMPLATES_DIR, $CURRENT_DIR;
        $filesystem = new Filesystem();
        if (!$filesystem->exists($TEMPLATES_DIR)) {
            echo "Директория $TEMPLATES_DIR не существует.\n";
            return;
        }

        $finder = new Finder();
        $templates = [];
        foreach ($finder->directories()->in($TEMPLATES_DIR) as $dir) {
            $templates[] = $dir->getRealPath();
        }
        if (empty($templates)) {
            echo "Шаблоны не найдены.\n";
            return;
        }

        echo "Доступные шаблоны:\n";
        foreach ($templates as $i => $template) {
            echo ($i + 1) . ". " . basename($template) . "\n";
        }

        $template_number = intval(readline("Введите номер шаблона: ")) - 1;
        if ($template_number < 0 || $template_number >= count($templates)) {
            echo "Неверный номер шаблона.\n";
            return;
        }

        $template_dir = $templates[$template_number];
        $module_name = readline("Введите имя нового модуля (snake_case): ");
        $camel_case_name = to_camel_case($module_name);
        $camel_case_lower_name = to_camel_case_lower($module_name);

        $new_module_dir = $CURRENT_DIR . '/' . $module_name;

        if (file_exists($new_module_dir)) {
            echo "Модуль $module_name уже существует.\n";
            return;
        }

        $filesystem = new Filesystem();
        try {
            $filesystem->mirror($template_dir, $new_module_dir);
        } catch (IOExceptionInterface $exception) {
            echo "Ошибка при копировании шаблона: " . $exception->getMessage() . "\n";
            return;
        }

        // Замена в именах файлов и папок
        $finder = new Finder();
        foreach ($finder->in($new_module_dir) as $file) {
            $new_path = str_replace(
                ["{{#ModuleName}}", "{{#moduleName}}", "{{#module_name}}"],
                [$camel_case_name, $camel_case_lower_name, $module_name],
                $file->getRealPath()
            );
            $filesystem->rename($file->getRealPath(), $new_path, true);
        }

        // Замена в содержимом файлов
        foreach ($finder->files()->in($new_module_dir) as $file) {
            $content = file_get_contents($file->getRealPath());
            $content = str_replace(
                ["{{#ModuleName}}", "{{#moduleName}}", "{{#module_name}}"],
                [$camel_case_name, $camel_case_lower_name, $module_name],
                $content
            );
            file_put_contents($file->getRealPath(), $content);
        }

        echo "Модуль $module_name создан на основе шаблона " . basename($template_dir) . ".\n";
    }


    public function show_help()
    {
        global $argv;
        echo "Использование: php " . $argv[0] . " <команда> [--script <путь к скрипту>]\n";
        echo "Команды:\n";
        echo "  init     Инициализация списка файлов и запись в JSON\n";
        echo "  install  Копирование файлов модуля в папку OpenCart и обновление JSON\n";
        echo "  dev      Режим наблюдения за изменениями\n";
        echo "  remove   Удаление файлов из OpenCart на основе JSON\n";
        echo "  create   Создание нового модуля по выбранному шаблону\n";
        echo "  help     Вывод справки\n";
    }

}