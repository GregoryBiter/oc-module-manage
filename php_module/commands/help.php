<?php

/**
 * Вывод справки.
 */
function show_help() {
    echo <<<HELP
Использование: oc-module <команда> [опции]

Команды:
  init        Инициализация списка файлов модуля и запись в JSON
  install     Копирование файлов модуля в папку OpenCart
  dev         Режим наблюдения за изменениями
  remove      Удаление файлов из OpenCart на основе JSON
  return      Возврат файлов из OpenCart в папку модуля
  create      Создание нового модуля по шаблону
  build       Сборка модуля на основе файла .build-module
  migrate     Миграция данных из старого формата в новый
  help        Вывод справки

Опции:
  --script    Путь к скрипту для запуска
  -a          В команде build создаёт ZIP-архив собранного модуля

Примеры:
  oc-module build -a    Собрать модуль и создать архив
  oc-module return      Вернуть файлы из OpenCart в папку модуля
  oc-module migrate     Перенести данные из старого формата в новый

Файлы:
  opencart-module.json  Метаданные модуля (имя, версия, автор и т.д.)
  .ocm_files.json       Список файлов модуля для отслеживания

HELP;

    // Список скриптов
    $scripts_dir = SCRIPT_DIR . '/scripts/';
    if (is_dir($scripts_dir)) {
        $scripts = array_diff(scandir($scripts_dir), array('.', '..'));
        if (!empty($scripts)) {
            echo "Скрипты (в папке scripts):\n";
            foreach ($scripts as $script) {
                echo "  " . str_pad(basename($script), 12) . " Запуск кастомного скрипта\n";
            }
            echo "\n";
        }
    }
}